<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\SessionActivityController;

Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);
    
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
});
