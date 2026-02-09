<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SessionActivityController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ExpenseRecurrenceController;
use App\Http\Controllers\ExpenseReportController;
use App\Http\Controllers\UserPointsController;
use App\Http\Controllers\ScorePointsSettingController;
use App\Http\Controllers\UserLevelController;
use App\Http\Controllers\SiteSettingController;
use App\Http\Controllers\UserPointBalanceController;
use App\Http\Controllers\ScorePointsTransactionController;
use App\Http\Controllers\UserRankController;
use App\Http\Controllers\SpinWheelController;
use App\Http\Controllers\SpinWheelSettingController;
use App\Http\Controllers\SpinWheelOptionController;
use App\Http\Controllers\SpinWheelClaimController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

// Rank & leaderboard - public (optional auth for admin/staff full user details)
Route::middleware('optional_sanctum')->group(function () {
    Route::get('points/leaderboard', [UserRankController::class, 'leaderboard']);
    Route::get('points/rank/{userId}', [UserRankController::class, 'userRank']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    // User points (loyalty) - accessible by authenticated users
    Route::prefix('points')->group(function () {
        Route::get('balance', [UserPointsController::class, 'balance']);
        Route::get('transactions', [UserPointsController::class, 'transactions']);
        Route::get('levels', [UserPointsController::class, 'levels']);
        Route::get('my-rank', [UserRankController::class, 'myRank']);
    });

    // Spin wheel (authenticated users)
    Route::prefix('spin-wheel')->group(function () {
        Route::get('status', [SpinWheelController::class, 'status']);
        Route::post('spin', [SpinWheelController::class, 'spin']);
        Route::post('choose', [SpinWheelController::class, 'choose']);
        Route::get('history', [SpinWheelController::class, 'history']);
        Route::get('my-claims', [SpinWheelController::class, 'claims']);
    });
    Route::get('avatars', [AuthController::class, 'avatarOptions']);
    Route::put('user', [AuthController::class, 'updateProfile']);
    Route::patch('user', [AuthController::class, 'updateProfile']);
    Route::post('user', [AuthController::class, 'updateProfile']); // POST with _method=PATCH for file uploads
    
    // User management routes with role-based access control
    Route::prefix('users')->group(function () {
        // Routes accessible by both Admin and Staff (read-only for staff)
        Route::middleware('admin_or_staff')->group(function () {
            Route::get('/', [UserController::class, 'index']); // List users
            Route::get('/{user}', [UserController::class, 'show']); // Show single user
            Route::get('/options/dropdown', [UserController::class, 'options']); // Get roles and statuses for dropdowns
            Route::post('/guest', [UserController::class, 'createGuest']); // Create guest user (for session creation)
        });
        
        // Routes accessible only by Admin (full CRUD operations)
        Route::middleware('admin')->group(function () {
            Route::post('/', [UserController::class, 'store']); // Create user
            Route::put('/{user}', [UserController::class, 'update']); // Update user
            Route::patch('/{user}', [UserController::class, 'update']); // Partial update user
            Route::post('/{user}', [UserController::class, 'update']); // Update user (POST with _method=PATCH for file uploads)
            Route::delete('/{user}', [UserController::class, 'destroy']); // Soft delete user
            Route::post('/{id}/restore', [UserController::class, 'restore']); // Restore soft deleted user
            Route::delete('/{id}/force', [UserController::class, 'forceDelete']); // Permanently delete user
        });
    });

    // Device management routes with role-based access control
    Route::prefix('devices')->group(function () {
        // Public route for available devices (accessible by all authenticated users)
        Route::get('/available', [DeviceController::class, 'available']); // Get available devices for booking
        
        // Routes accessible by both Admin and Staff (read-only for staff)
        Route::middleware('admin_or_staff')->group(function () {
            Route::get('/', [DeviceController::class, 'index']); // List devices
            Route::get('/{device}', [DeviceController::class, 'show']); // Show single device
            Route::get('/options/dropdown', [DeviceController::class, 'options']); // Get device types and statuses for dropdowns
            Route::get('/reports/statistics', [DeviceController::class, 'statistics']); // Get device statistics
            Route::patch('/{device}/status', [DeviceController::class, 'updateStatus']); // Update device status (admin & staff)
        });
        
        // Routes accessible only by Admin (full CRUD operations)
        Route::middleware('admin')->group(function () {
            Route::post('/', [DeviceController::class, 'store']); // Create device
            Route::put('/{device}', [DeviceController::class, 'update']); // Update device
            Route::patch('/{device}', [DeviceController::class, 'update']); // Partial update device
            Route::delete('/{device}', [DeviceController::class, 'destroy']); // Soft delete device
            Route::post('/{id}/restore', [DeviceController::class, 'restore']); // Restore soft deleted device
            Route::delete('/{id}/force', [DeviceController::class, 'forceDelete']); // Permanently delete device
        });
    });

    // Product management routes with role-based access control
    Route::prefix('products')->middleware(['auth:sanctum', 'admin_or_staff'])->group(function () {
        Route::get('/', [ProductController::class, 'index']); // List products
        Route::get('/categories', [ProductController::class, 'categories']); // Get product categories (for expense UI)
        Route::get('/{id}', [ProductController::class, 'show']); // Show single product
        Route::post('/', [ProductController::class, 'store']); // Create product
        Route::put('/{id}', [ProductController::class, 'update']); // Update product
        Route::delete('/{id}', [ProductController::class, 'destroy']); // Delete product
    });

    // Session management routes with role-based access control
    Route::prefix('sessions')->group(function () {
        // Routes accessible by both Admin and Staff (read-only for staff)
        Route::middleware('admin_or_staff')->group(function () {
            Route::get('/', [SessionController::class, 'index']); // List sessions
            Route::get('/date/{date}', [SessionController::class, 'getByStartDate']); // Get sessions by start date (Y-m-d format)
            Route::get('/customer/{customerId}', [SessionController::class, 'getByCustomer']); // Get customer sessions
            Route::get('/status/{status}', [SessionController::class, 'getByStatus']); // Get sessions by status
            Route::get('/{id}', [SessionController::class, 'show']); // Show single session
            Route::get('/{id}/users', [SessionController::class, 'getSessionUsers']); // Get users in session
        });
        
        // Routes accessible only by Admin (full CRUD operations)
        Route::middleware('admin')->group(function () {
            Route::post('/', [SessionController::class, 'store']); // Create session
            Route::put('/{id}', [SessionController::class, 'update']); // Update session
            Route::patch('/{id}', [SessionController::class, 'update']); // Partial update session
            Route::delete('/{id}', [SessionController::class, 'destroy']); // Delete session
        });

        // Status management routes accessible by both Admin and Staff
        Route::middleware('admin_or_staff')->group(function () {
            Route::patch('/{id}/end', [SessionController::class, 'end']); // End session
            Route::patch('/{id}/pause', [SessionController::class, 'pause']); // Pause session
            Route::patch('/{id}/resume', [SessionController::class, 'resume']); // Resume session
        });
    });

    // Session Activities management routes
    Route::prefix('sessions/{sessionId}/activities')->middleware('admin_or_staff')->group(function () {
        Route::get('/', [SessionActivityController::class, 'index']); // List activities in session
        Route::get('/{id}/history', [SessionActivityController::class, 'getActivityHistory']); // Activity history (mode/pause/products timeline)
        Route::get('/{id}', [SessionActivityController::class, 'show']); // Show specific activity
        Route::get('/type/{type}', [SessionActivityController::class, 'getByType']); // Get activities by type
        
        Route::middleware('admin')->group(function () {
            Route::post('/', [SessionActivityController::class, 'store']); // Create activity
            Route::put('/{id}', [SessionActivityController::class, 'update']); // Update activity
            Route::patch('/{id}', [SessionActivityController::class, 'update']); // Partial update activity
            Route::delete('/{id}', [SessionActivityController::class, 'destroy']); // Delete activity
        });

        // Activity status management routes accessible by both Admin and Staff
        Route::middleware('admin_or_staff')->group(function () {
            Route::patch('/{id}/status', [SessionActivityController::class, 'updateStatus']); // Update activity status
            Route::patch('/{id}/end', [SessionActivityController::class, 'end']); // End activity
            Route::patch('/{id}/calculate-duration', [SessionActivityController::class, 'calculateDuration']); // Calculate duration
        });

        // Activity users management routes
        Route::prefix('{activityId}/users')->middleware('admin')->group(function () {
            Route::get('/available', [SessionActivityController::class, 'getAvailableUsers']); // Get available users for activity
            Route::post('/', [SessionActivityController::class, 'addUser']); // Add user to activity
            Route::delete('/{userId}', [SessionActivityController::class, 'removeUser']); // Remove user from activity
        });

        // Activity products management routes
        Route::prefix('{activityId}/products')->group(function () {
            // Read routes accessible by both Admin and Staff
            Route::middleware('admin_or_staff')->group(function () {
                Route::get('/', [SessionActivityController::class, 'getActivityProducts']); // Get all products for activity
                Route::get('/user/{userId}', [SessionActivityController::class, 'getActivityProductsByUser']); // Get products by user for activity
            });
            
            // Write routes accessible only by Admin
            Route::middleware('admin')->group(function () {
                Route::post('/', [SessionActivityController::class, 'addProductToActivity']); // Add product to activity
                Route::put('/{productOrderId}', [SessionActivityController::class, 'updateActivityProduct']); // Update product order
                Route::delete('/{productOrderId}', [SessionActivityController::class, 'deleteActivityProduct']); // Delete product order
            });
        });
    });

    // Expense Categories management routes (Admin & Staff full CRUD)
    Route::prefix('expense-categories')->middleware('admin_or_staff')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'index']); // List all categories
        Route::get('/paginated', [ExpenseCategoryController::class, 'indexPaginated']); // List categories (paginated)
        Route::get('/main', [ExpenseCategoryController::class, 'getMainCategories']); // Get main categories
        Route::get('/active', [ExpenseCategoryController::class, 'getActiveCategories']); // Get active categories
        Route::get('/{id}', [ExpenseCategoryController::class, 'show']); // Show single category
        Route::get('/{id}/sub-categories', [ExpenseCategoryController::class, 'getSubCategories']); // Get sub-categories
        Route::post('/', [ExpenseCategoryController::class, 'store']); // Create category
        Route::put('/{id}', [ExpenseCategoryController::class, 'update']); // Update category
        Route::patch('/{id}', [ExpenseCategoryController::class, 'update']); // Partial update category
        Route::delete('/{id}', [ExpenseCategoryController::class, 'destroy']); // Delete category
        Route::patch('/{id}/deactivate', [ExpenseCategoryController::class, 'deactivate']); // Deactivate category
        Route::patch('/{id}/activate', [ExpenseCategoryController::class, 'activate']); // Activate category
    });

    // Expenses management routes (Admin & Staff full CRUD)
    Route::prefix('expenses')->middleware('admin_or_staff')->group(function () {
        Route::get('/', [ExpenseController::class, 'index']); // List expenses
        Route::get('/date-range', [ExpenseController::class, 'getByDateRange']); // Get expenses by date range
        Route::get('/category/{categoryId}', [ExpenseController::class, 'getByCategory']); // Get expenses by category
        Route::get('/status/{status}', [ExpenseController::class, 'getByStatus']); // Get expenses by status (paid/unpaid)
        Route::get('/recurring', [ExpenseController::class, 'getRecurring']); // Get recurring expenses
        Route::get('/{id}', [ExpenseController::class, 'show']); // Show single expense
        Route::post('/', [ExpenseController::class, 'store']); // Create expense
        Route::put('/{id}', [ExpenseController::class, 'update']); // Update expense
        Route::patch('/{id}', [ExpenseController::class, 'update']); // Partial update expense
        Route::delete('/{id}', [ExpenseController::class, 'destroy']); // Delete expense
        Route::patch('/{id}/mark-paid', [ExpenseController::class, 'markAsPaid']); // Mark as paid
        Route::patch('/{id}/mark-unpaid', [ExpenseController::class, 'markAsUnpaid']); // Mark as unpaid
        
        // Expense attachments
        Route::get('/{id}/attachments', [ExpenseController::class, 'getAttachments']); // Get attachments
        Route::post('/{id}/attachments', [ExpenseController::class, 'uploadAttachment']); // Upload attachment
        Route::delete('/{id}/attachments/{attachmentId}', [ExpenseController::class, 'deleteAttachment']); // Delete attachment
    });

    // Expense Recurrences management routes (Admin & Staff full CRUD)
    Route::prefix('expense-recurrences')->middleware('admin_or_staff')->group(function () {
        Route::get('/', [ExpenseRecurrenceController::class, 'index']); // List recurrences
        Route::get('/active', [ExpenseRecurrenceController::class, 'getActive']); // Get active recurrences
        Route::get('/overdue', [ExpenseRecurrenceController::class, 'getOverdue']); // Get overdue recurrences
        Route::get('/due-within', [ExpenseRecurrenceController::class, 'getDueWithin']); // Get recurrences due within X days
        Route::get('/{id}', [ExpenseRecurrenceController::class, 'show']); // Show single recurrence
        Route::post('/', [ExpenseRecurrenceController::class, 'store']); // Create recurrence
        Route::put('/{id}', [ExpenseRecurrenceController::class, 'update']); // Update recurrence
        Route::patch('/{id}', [ExpenseRecurrenceController::class, 'update']); // Partial update recurrence
        Route::delete('/{id}', [ExpenseRecurrenceController::class, 'destroy']); // Delete recurrence
        Route::patch('/{id}/deactivate', [ExpenseRecurrenceController::class, 'deactivate']); // Deactivate recurrence
        Route::patch('/{id}/activate', [ExpenseRecurrenceController::class, 'activate']); // Activate recurrence
        Route::post('/{id}/confirm-payment', [ExpenseRecurrenceController::class, 'confirmPayment']); // Confirm payment (creates expense)
    });

    // Site settings (Admin only)
    Route::prefix('site-settings')->middleware('admin')->group(function () {
        Route::get('/', [SiteSettingController::class, 'index']);
        Route::put('/', [SiteSettingController::class, 'update']);
        Route::patch('/', [SiteSettingController::class, 'update']);
    });

    // Spin wheel admin (Admin only)
    Route::prefix('spin-wheel')->middleware('admin')->group(function () {
        Route::get('settings', [SpinWheelSettingController::class, 'show']);
        Route::put('settings', [SpinWheelSettingController::class, 'update']);
        Route::patch('settings', [SpinWheelSettingController::class, 'update']);
        Route::apiResource('options', SpinWheelOptionController::class);
        Route::get('admin/history', [SpinWheelController::class, 'adminHistory']);
        Route::get('claims', [SpinWheelClaimController::class, 'index']);
        Route::get('claims/{spinWheelClaim}', [SpinWheelClaimController::class, 'show']);
        Route::patch('claims/{spinWheelClaim}/fulfill', [SpinWheelClaimController::class, 'fulfill']);
    });

    // Score points settings (Admin only)
    Route::prefix('score-points-settings')->middleware('admin')->group(function () {
        Route::get('/', [ScorePointsSettingController::class, 'show']);
        Route::put('/', [ScorePointsSettingController::class, 'update']);
        Route::patch('/', [ScorePointsSettingController::class, 'update']);
    });

    // User point balances (Admin only)
    Route::prefix('user-point-balances')->middleware('admin')->group(function () {
        Route::get('/', [UserPointBalanceController::class, 'index']);
        Route::post('/{userId}/adjust', [UserPointBalanceController::class, 'adjust']);
    });

    // Score points transactions (Admin only) - all transactions, filter by user
    Route::prefix('score-points-transactions')->middleware('admin')->group(function () {
        Route::get('/', [ScorePointsTransactionController::class, 'index']);
    });

    // User levels - read visible to any role; create/update/delete admin only
    Route::prefix('user-levels')->group(function () {
        Route::get('/', [UserLevelController::class, 'index']);
        Route::get('/{userLevel}', [UserLevelController::class, 'show']);
        Route::middleware('admin')->group(function () {
            Route::post('/', [UserLevelController::class, 'store']);
            Route::put('/{userLevel}', [UserLevelController::class, 'update']);
            Route::patch('/{userLevel}', [UserLevelController::class, 'update']);
            Route::delete('/{userLevel}', [UserLevelController::class, 'destroy']);
        });
    });

    // Expense Reports routes (Admin & Staff read-only)
    Route::prefix('expense-reports')->middleware('admin_or_staff')->group(function () {
        Route::get('/summary', [ExpenseReportController::class, 'getSummary']); // Summary report
        Route::get('/by-category', [ExpenseReportController::class, 'getByCategory']); // By category report
        Route::get('/paid-vs-unpaid', [ExpenseReportController::class, 'getPaidVsUnpaid']); // Paid vs unpaid report
        Route::get('/monthly', [ExpenseReportController::class, 'getMonthlySummary']); // Monthly summary
        Route::get('/upcoming-recurring', [ExpenseReportController::class, 'getUpcomingRecurring']); // Upcoming recurring
        Route::get('/overdue-recurring', [ExpenseReportController::class, 'getOverdueRecurring']); // Overdue recurring
    });
});
