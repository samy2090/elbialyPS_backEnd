<?php

namespace App\Http\Requests;

use App\Enums\DeviceType;
use App\Enums\DeviceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeviceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization is handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $device = $this->route('device');
        
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('devices', 'name')->ignore($device->id)],
            'description' => ['sometimes', 'nullable', 'string'],
            'device_type' => ['sometimes', 'required', Rule::in(DeviceType::values())],
            'status' => ['sometimes', Rule::in(DeviceStatus::values())],
            'price_per_hour' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999.99'],
            'multi_price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:99999.99'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The device name is required.',
            'name.unique' => 'A device with this name already exists.',
            'device_type.required' => 'The device type is required.',
            'device_type.in' => 'The selected device type is invalid.',
            'status.in' => 'The selected status is invalid.',
            'price_per_hour.required' => 'The price per hour is required.',
            'price_per_hour.numeric' => 'The price per hour must be a valid number.',
            'price_per_hour.min' => 'The price per hour must be at least $0.00.',
            'price_per_hour.max' => 'The price per hour cannot exceed $9,999.99.',
            'multi_price.numeric' => 'The multi-hour price must be a valid number.',
            'multi_price.min' => 'The multi-hour price must be at least $0.00.',
            'multi_price.max' => 'The multi-hour price cannot exceed $99,999.99.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'device_type' => 'device type',
            'price_per_hour' => 'price per hour',
            'multi_price' => 'multi-hour price',
        ];
    }
}
