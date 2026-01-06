<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDeviceRequest;
use App\Http\Requests\UpdateDeviceRequest;
use App\Models\Device;
use App\Enums\DeviceType;
use App\Enums\DeviceStatus;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DeviceController extends Controller
{
    /**
     * Display a listing of devices.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Device::query();
        
        // Apply filters
        if ($request->has('device_type')) {
            $query->where('device_type', $request->device_type);
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }
        
        // Filter by availability
        if ($request->has('available_only') && $request->available_only) {
            $query->where('status', DeviceStatus::AVAILABLE->value);
        }
        
        // Price range filter (in Egyptian Pounds)
        if ($request->has('min_price')) {
            $query->where('price_per_hour', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('price_per_hour', '<=', $request->max_price);
        }
        
        // Include soft deleted items for admin users only
        if ($request->has('include_deleted') && $request->include_deleted && auth()->user()->isAdmin()) {
            $query->withTrashed();
        }
        
        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $perPage = $request->get('per_page', 15);
        $devices = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $devices
        ]);
    }

    /**
     * Store a newly created device in storage.
     * Only accessible by admin users.
     */
    public function store(StoreDeviceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        
        // Prices are stored directly in Egyptian Pounds (EGP)
        $device = Device::create($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Device created successfully',
            'data' => $device
        ], 201);
    }

    /**
     * Display the specified device.
     */
    public function show(Device $device): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $device
        ]);
    }

    /**
     * Update the specified device in storage.
     * Only accessible by admin users.
     */
    public function update(UpdateDeviceRequest $request, Device $device): JsonResponse
    {
        $validated = $request->validated();
        
        // Prices are stored directly in Egyptian Pounds (EGP)
        $device->update($validated);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Device updated successfully',
            'data' => $device->fresh()
        ]);
    }

    /**
     * Remove the specified device from storage (soft delete).
     * Only accessible by admin users.
     */
    public function destroy(Device $device): JsonResponse
    {
        // Check if device is currently in use
        if ($device->isInUse()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete device that is currently in use'
            ], 422);
        }
        
        $device->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Device deleted successfully'
        ]);
    }

    /**
     * Restore a soft deleted device.
     * Only accessible by admin users.
     */
    public function restore($id): JsonResponse
    {
        $device = Device::withTrashed()->findOrFail($id);
        $device->restore();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Device restored successfully',
            'data' => $device
        ]);
    }

    /**
     * Force delete a device permanently.
     * Only accessible by admin users.
     */
    public function forceDelete($id): JsonResponse
    {
        $device = Device::withTrashed()->findOrFail($id);
        $device->forceDelete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Device permanently deleted'
        ]);
    }

    /**
     * Update device status.
     * Accessible by admin and staff users.
     */
    public function updateStatus(Request $request, Device $device): JsonResponse
    {
        $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', DeviceStatus::values())],
        ]);
        
        $device->update(['status' => $request->status]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Device status updated successfully',
            'data' => $device->fresh()
        ]);
    }

    /**
     * Get all available device types and statuses for dropdowns.
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'device_types' => DeviceType::options(),
                'statuses' => DeviceStatus::options()
            ]
        ]);
    }

    /**
     * Get devices statistics.
     * Accessible by admin and staff users.
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_devices' => Device::count(),
            'available_devices' => Device::where('status', DeviceStatus::AVAILABLE)->count(),
            'in_use_devices' => Device::where('status', DeviceStatus::IN_USE)->count(),
            'maintenance_devices' => Device::where('status', DeviceStatus::MAINTENANCE)->count(),
            'devices_by_type' => [
                'ps4' => Device::where('device_type', DeviceType::PS4)->count(),
                'ps5' => Device::where('device_type', DeviceType::PS5)->count(),
                'billboard' => Device::where('device_type', DeviceType::BILLBOARD)->count(),
            ],
            'average_price_per_hour' => Device::avg('price_per_hour'), // In Egyptian Pounds
        ];
        
        // Add soft deleted count for admin users
        if (auth()->user()->isAdmin()) {
            $stats['deleted_devices'] = Device::onlyTrashed()->count();
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Get available devices for booking.
     * Accessible by all authenticated users.
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function available(Request $request): JsonResponse
    {
        $query = Device::available();
        
        // Optional filter by device type
        if ($request->has('device_type')) {
            $query->where('device_type', $request->device_type);
        }
        
        // Get devices with selected fields
        $devices = $query
            ->select(['id', 'name', 'description', 'device_type', 'status', 'price_per_hour', 'multi_price'])
            ->orderBy('device_type')
            ->orderBy('name')
            ->get();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Available devices retrieved successfully',
            'data' => $devices,
            'count' => $devices->count()
        ]);
    }
}
