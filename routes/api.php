<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\ProductController;

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
});
