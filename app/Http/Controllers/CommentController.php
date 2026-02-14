<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCommentRequest;
use App\Http\Requests\UpdateCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    /**
     * Admin: list all comments with filters.
     * Query: user_id=, post_id=, date_from=Y-m-d, date_to=Y-m-d, per_page=, page=
     */
    public function adminIndex(Request $request): JsonResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin only.'], Response::HTTP_FORBIDDEN);
        }

        $perPage = min((int) $request->get('per_page', 15), 50);
        $perPage = max($perPage, 1);

        $query = Comment::query()->with(['user', 'post' => fn ($q) => $q->select('id', 'body', 'user_id', 'status', 'created_at')]);

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('post_id')) {
            $query->where('post_id', $request->post_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $query->orderByDesc('created_at');
        $comments = $query->paginate($perPage);

        return response()->json([
            'data' => CommentResource::collection($comments->items()),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'per_page' => $comments->perPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    /**
     * List comments for a post (public, optional auth). Top-level only; load replies (max depth 2).
     * Query: cursor=, per_page=10. Returns 10 top-level comments per page; each includes replies.
     */
    public function index(Request $request, Post $post): JsonResponse
    {
        if ($post->status !== \App\Enums\PostStatus::PUBLISHED && (!$request->user() || ($request->user()->id !== $post->user_id && !$request->user()->isAdmin()))) {
            return response()->json(['message' => 'Post not found.'], Response::HTTP_NOT_FOUND);
        }

        $perPage = min((int) $request->get('per_page', config('posts.comments_per_page', 10)), 50);
        $perPage = max($perPage, 1);

        $query = Comment::query()
            ->where('post_id', $post->id)
            ->whereNull('parent_id')
            ->with(['user', 'replies' => fn ($q) => $q->with('user')])
            ->orderBy('created_at')
            ->orderBy('id');

        $cursor = $request->get('cursor');
        if ($cursor) {
            $query->where('id', '>', $cursor);
        }

        $comments = $query->take($perPage + 1)->get();
        $hasMore = $comments->count() > $perPage;
        if ($hasMore) {
            $comments = $comments->take($perPage);
        }
        $nextCursor = $hasMore ? $comments->last()->id : null;

        return response()->json([
            'data' => CommentResource::collection($comments),
            'meta' => [
                'next_cursor' => $nextCursor,
                'has_more' => $hasMore,
            ],
        ]);
    }

    /**
     * Create comment (auth). Optional parent_id for reply (max depth 2).
     */
    public function store(StoreCommentRequest $request, Post $post): JsonResponse
    {
        if ($post->status !== \App\Enums\PostStatus::PUBLISHED && $post->user_id !== $request->user()->id && !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Cannot comment on this post.'], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validated();
        $parentId = $validated['parent_id'] ?? null;
        if ($parentId) {
            $parent = Comment::find($parentId);
            if (!$parent || $parent->post_id !== $post->id) {
                return response()->json(['message' => 'Invalid parent comment.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'parent_id' => $parentId,
            'body' => $validated['body'],
        ]);

        $comment->load(['user', 'replies']);
        return response()->json(['data' => new CommentResource($comment)], Response::HTTP_CREATED);
    }

    /**
     * Update comment (owner or admin). Set edited_at, edited_by.
     */
    public function update(UpdateCommentRequest $request, Post $post, Comment $comment): JsonResponse
    {
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found.'], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();
        $comment->body = $validated['body'] ?? $comment->body;
        $comment->edited_at = now();
        $comment->edited_by = $request->user()->id;
        $comment->save();

        $comment->load(['user', 'replies']);
        return response()->json(['data' => new CommentResource($comment)]);
    }

    /**
     * Admin: delete comment by ID (no post required). Soft delete.
     */
    public function adminDestroy(Request $request, Comment $comment): JsonResponse
    {
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json(['message' => 'Admin only.'], Response::HTTP_FORBIDDEN);
        }
        $this->authorize('delete', $comment);
        $comment->delete();
        return response()->json(['message' => 'Comment deleted.']);
    }

    /**
     * Delete comment (owner or admin). Soft delete.
     */
    public function destroy(Request $request, Post $post, Comment $comment): JsonResponse
    {
        if ($comment->post_id !== $post->id) {
            return response()->json(['message' => 'Comment not found.'], Response::HTTP_NOT_FOUND);
        }
        $this->authorize('delete', $comment);
        $comment->delete();
        return response()->json(['message' => 'Comment deleted.']);
    }
}
