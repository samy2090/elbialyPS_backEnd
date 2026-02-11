<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Repositories\UserRepositoryInterface;
use App\Repositories\UserRepository;
use App\Services\AuthServiceInterface;
use App\Services\AuthService;
use App\Repositories\ProductRepositoryInterface;
use App\Repositories\ProductRepository;
use App\Repositories\SessionRepositoryInterface;
use App\Repositories\SessionRepository;
use App\Repositories\SessionActivityRepositoryInterface;
use App\Repositories\SessionActivityRepository;
use App\Repositories\ExpenseCategoryRepositoryInterface;
use App\Repositories\ExpenseCategoryRepository;
use App\Repositories\ExpenseRepositoryInterface;
use App\Repositories\ExpenseRepository;
use App\Repositories\ExpenseRecurrenceRepositoryInterface;
use App\Repositories\ExpenseRecurrenceRepository;
use App\Models\ActivityProduct;
use App\Models\Comment;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpenseAttachment;
use App\Models\PostReaction;
use App\Observers\ActivityProductPointsObserver;
use App\Observers\CommentObserver;
use App\Observers\ExpenseObserver;
use App\Observers\ExpenseCategoryObserver;
use App\Observers\ExpenseAttachmentObserver;
use App\Observers\PostReactionObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(AuthServiceInterface::class, AuthService::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(SessionRepositoryInterface::class, SessionRepository::class);
        $this->app->bind(SessionActivityRepositoryInterface::class, SessionActivityRepository::class);
        
        // Expense management repositories
        $this->app->bind(ExpenseCategoryRepositoryInterface::class, ExpenseCategoryRepository::class);
        $this->app->bind(ExpenseRepositoryInterface::class, ExpenseRepository::class);
        $this->app->bind(ExpenseRecurrenceRepositoryInterface::class, ExpenseRecurrenceRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register expense management observers
        Expense::observe(ExpenseObserver::class);
        ExpenseCategory::observe(ExpenseCategoryObserver::class);
        ExpenseAttachment::observe(ExpenseAttachmentObserver::class);

        // Register points observer for activity products
        ActivityProduct::observe(ActivityProductPointsObserver::class);

        // Posts: keep denormalized counts in sync
        PostReaction::observe(PostReactionObserver::class);
        Comment::observe(CommentObserver::class);
    }
}

