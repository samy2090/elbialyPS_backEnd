<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateSessionActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'session_id' => 'nullable|integer|exists:game_sessions,id', // Optional - will be set from route parameter
            'type' => 'nullable|in:playing,chillout',
            'activity_type' => 'nullable|in:device_use,pause',
            'device_id' => 'nullable|integer|exists:devices,id',
            'mode' => 'nullable|in:single,multi',
            'started_at' => 'nullable|date_format:Y-m-d H:i:s',
            'duration' => 'nullable|numeric|min:0', // Duration in hours (for calculating ended_at)
            'status' => 'nullable|in:active,paused,ended', // Activity status
        ];
    }
}
