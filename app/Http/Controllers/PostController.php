<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Http\Requests\ReactToPostRequest;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\PostReaction;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Public feed (optional auth for current_user_reaction, can_edit, can_delete).
     * Query: sort=newest|most_reacted|most_commented, author_id=, cursor= (id), per_page=10
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', config('posts.feed_per_page', 10)), 50);
        $perPage = max($perPage, 1);

        $query = Post::query()
            ->where('status', PostStatus::PUBLISHED)
            ->with(['user', 'taggedUsers', 'media']);

        if ($request->filled('author_id')) {
            $query->where('user_id', $request->author_id);
        }

        $sort = $request->get('sort', 'newest');
        if ($sort === 'most_reacted') {
            $query->orderByDesc('reactions_count')->orderByDesc('created_at');
        } elseif ($sort === 'most_commented') {
            $query->orderByDesc('comments_count')->orderByDesc('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $cursor = $request->get('cursor');
        if ($cursor) {
            $query->where('id', '<', $cursor);
        }

        $posts = $query->take($perPage + 1)->get();
        $hasMore = $posts->count() > $perPage;
        if ($hasMore) {
            $posts = $posts->take($perPage);
        }
        $nextCursor = $hasMore ? $posts->last()->id : null;

        $user = $request->user();
        if ($user) {
            $postIds = $posts->pluck('id')->toArray();
            $reactions = PostReaction::whereIn('post_id', $postIds)->where('user_id', $user->id)->get()->keyBy('post_id');
            foreach ($posts as $post) {
                $post->setRelation('reactions', $reactions->get($post->id) ? collect([$reactions->get($post->id)]) : collect());
            }
        }

        return response()->json([
            'data' => PostResource::collection($posts),
            'meta' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
        ]);
    }

    /**
     * Single post (public, optional auth).
     */
    public function show(Request $request, Post $post): JsonResponse
    {
        if ($post->status !== PostStatus::PUBLISHED && (!$request->user() || ($request->user()->id !== $post->user_id && !$request->user()->isAdmin()))) {
            return response()->json(['message' => 'Post not found.'], Response::HTTP_NOT_FOUND);
        }

        $post->load(['user', 'taggedUsers', 'media']);
        if ($request->user()) {
            $post->load(['reactions' => fn ($q) => $q->where('user_id', $request->user()->id)]);
        }

        return response()->json(['data' => new PostResource($post)]);
    }

    /**
     * Create post (auth). Rate limit 5 per hour. Images and tagged_user_ids.
     */
    public function store(StorePostRequest $request): JsonResponse
    {
        $limit = config('posts.post_rate_limit_per_hour', 5);
        $count = Post::where('user_id', $request->user()->id)
            ->where('created_at', '>=', now()->subHour())
            ->count();
        if ($count >= $limit) {
            return response()->json([
                'message' => 'You may only create ' . $limit . ' posts per hour.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $requireApproval = (bool) SiteSetting::get('posts_require_approval', false);
        $status = $requireApproval ? PostStatus::PENDING : PostStatus::PUBLISHED;

        $validated = $request->validated();
        $taggedIds = $validated['tagged_user_ids'] ?? [];
        $images = $request->file('images');
        if (!is_array($images)) {
            $images = $images ? [$images] : [];
        }

        $post = DB::transaction(function () use ($request, $validated, $taggedIds, $images, $status) {
            $post = Post::create([
                'user_id' => $request->user()->id,
                'body' => $validated['body'],
                'status' => $status,
            ]);

            if (!empty($taggedIds)) {
                $post->taggedUsers()->sync(array_unique($taggedIds));
            }

            $order = 0;
            foreach ($images as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }
                $path = $file->store('posts/' . $post->id, 'public');
                PostMedia::create(['post_id' => $post->id, 'path' => $path, 'order' => $order++]);
            }

            return $post;
        });

        $post->load(['user', 'taggedUsers', 'media']);
        return response()->json(['data' => new PostResource($post)], Response::HTTP_CREATED);
    }

    /**
     * Update post (owner or admin). Set edited_at, edited_by.
     */
    public function update(UpdatePostRequest $request, Post $post): JsonResponse
    {
        $validated = $request->validated();
        $taggedIds = $validated['tagged_user_ids'] ?? null;
        $images = $request->file('images') ?? [];

        DB::transaction(function () use ($request, $post, $validated, $taggedIds, $images) {
            if (array_key_exists('body', $validated)) {
                $post->body = $validated['body'];
            }
            $post->edited_at = now();
            $post->edited_by = $request->user()->id;
            $post->save();

            if ($taggedIds !== null) {
                $post->taggedUsers()->sync(array_unique($taggedIds));
            }

            if ($request->hasFile('images')) {
                foreach ($post->media as $m) {
                    Storage::disk('public')->delete($m->path);
                }
                $post->media()->delete();
                $order = 0;
                foreach ($images as $file) {
                    if (!$file || !$file->isValid()) {
                        continue;
                    }
                    $path = $file->store('posts/' . $post->id, 'public');
                    PostMedia::create(['post_id' => $post->id, 'path' => $path, 'order' => $order++]);
                }
            }
        });

        $post->load(['user', 'taggedUsers', 'media']);
        return response()->json(['data' => new PostResource($post)]);
    }

    /**
     * Delete post (owner or admin). Soft delete.
     */
    public function destroy(Request $request, Post $post): JsonResponse
    {
        $this->authorize('delete', $post);
        $post->delete();
        return response()->json(['message' => 'Post deleted.']);
    }

    /**
     * Admin: list all posts with filters.
     * Query: status=all|pending|published, user_id=, date_from=Y-m-d, date_to=Y-m-d, per_page=, page=
     */
    public function adminList(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin only.'], Response::HTTP_FORBIDDEN);
        }

        $perPage = min((int) $request->get('per_page', 15), 50);
        $perPage = max($perPage, 1);

        $query = Post::query()->with(['user', 'taggedUsers', 'media']);

        $status = $request->get('status', 'all');
        if ($status === 'pending') {
            $query->where('status', PostStatus::PENDING);
        } elseif ($status === 'published') {
            $query->where('status', PostStatus::PUBLISHED);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $query->orderByDesc('created_at');
        $posts = $query->paginate($perPage);

        return response()->json([
            'data' => PostResource::collection($posts->items()),
            'meta' => [
                'current_page' => $posts->currentPage(),
                'last_page' => $posts->lastPage(),
                'per_page' => $posts->perPage(),
                'total' => $posts->total(),
            ],
        ]);
    }

    /**
     * Admin: list pending posts.
     */
    public function pending(Request $request): JsonResponse
    {
        $posts = Post::query()
            ->where('status', PostStatus::PENDING)
            ->with(['user', 'taggedUsers', 'media'])
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 15), 50));

        return response()->json(['data' => PostResource::collection($posts->items()), 'meta' => [
            'current_page' => $posts->currentPage(),
            'last_page' => $posts->lastPage(),
            'per_page' => $posts->perPage(),
            'total' => $posts->total(),
        ]]);
    }

    /**
     * Admin: approve pending post.
     */
    public function approve(Request $request, Post $post): JsonResponse
    {
        $this->authorize('approve', \App\Models\Post::class);
        if ($post->status !== PostStatus::PENDING) {
            return response()->json(['message' => 'Post is not pending.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $post->update(['status' => PostStatus::PUBLISHED]);
        $post->load(['user', 'taggedUsers', 'media']);
        return response()->json(['data' => new PostResource($post)]);
    }

    /**
     * Add or update reaction (auth). One per user per post.
     */
    public function react(ReactToPostRequest $request, Post $post): JsonResponse
    {
        $user = $request->user();
        $type = $request->validated()['reaction_type'];

        $reaction = PostReaction::updateOrCreate(
            ['post_id' => $post->id, 'user_id' => $user->id],
            ['reaction_type' => $type]
        );

        $post->refresh();
        $post->load(['user', 'taggedUsers', 'media']);
        $post->load(['reactions' => fn ($q) => $q->where('user_id', $user->id)]);

        return response()->json(['data' => new PostResource($post)]);
    }

    /**
     * Remove reaction (auth).
     */
    public function removeReaction(Request $request, Post $post): JsonResponse
    {
        $user = $request->user();
        PostReaction::where('post_id', $post->id)->where('user_id', $user->id)->delete();
        $post->refresh();
        $post->load(['user', 'taggedUsers', 'media']);
        return response()->json(['data' => new PostResource($post)]);
    }

    /**
     * Current user's posts (auth). Includes pending.
     */
    public function myPosts(Request $request): JsonResponse
    {
        $perPage = min((int) $request->get('per_page', config('posts.feed_per_page', 10)), 50);
        $posts = Post::query()
            ->where('user_id', $request->user()->id)
            ->with(['user', 'taggedUsers', 'media'])
            ->orderByDesc('created_at')
            ->cursorPaginate($perPage);

        $items = $posts->items();
        if ($request->user() && count($items) > 0) {
            $ids = collect($items)->pluck('id')->toArray();
            $reactions = PostReaction::whereIn('post_id', $ids)->where('user_id', $request->user()->id)->get()->groupBy('post_id');
            foreach ($items as $p) {
                $p->setRelation('reactions', $reactions->get($p->id, collect())->values());
            }
        }

        return response()->json([
            'data' => PostResource::collection($items),
            'meta' => [
                'next_cursor' => $posts->nextCursor()?->encode(),
                'has_more' => $posts->hasMorePages(),
            ],
        ]);
    }
}
