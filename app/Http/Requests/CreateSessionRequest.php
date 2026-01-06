<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'created_by' => 'required|integer|exists:users,id',
            'customer_id' => 'required|integer|exists:users,id',
            'started_at' => 'nullable|date_format:Y-m-d H:i:s',
            'duration' => 'nullable|numeric|min:0', // Duration in hours
            'device_id' => 'nullable|integer|exists:devices,id',
            'mode' => 'nullable|in:single,multi', // Activity mode
            'activity_data' => 'nullable|array',
            'activity_data.mode' => 'nullable|in:single,multi', // Activity mode from nested object
            'status' => 'nullable|in:active,paused,ended',
            'type' => 'nullable|in:playing,chillout',
            'total_price' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
        ];
    }
    
    /**
     * Prepare the data for validation.
     * Extract mode from activity_data if it exists and merge it to root level.
     */
    protected function prepareForValidation(): void
    {
        // If mode is in activity_data but not at root level, move it to root
        if (isset($this->activity_data['mode']) && !isset($this->mode)) {
            $this->merge([
                'mode' => $this->activity_data['mode']
            ]);
        }
    }
}
