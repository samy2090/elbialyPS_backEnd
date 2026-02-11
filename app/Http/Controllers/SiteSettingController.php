<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class SiteSettingController extends Controller
{
    /**
     * Expected site setting keys and their configuration
     */
    protected array $keys = [
        'site_name' => ['type' => 'string', 'group' => 'general'],
        'site_logo' => ['type' => 'string', 'group' => 'general'],
        'place_status' => ['type' => 'string', 'group' => 'general'], // open | closed
        'maintenance_mode' => ['type' => 'boolean', 'group' => 'general'],
        'discount_percent' => ['type' => 'decimal', 'group' => 'discount'],
        'discount_start_at' => ['type' => 'string', 'group' => 'discount'], // Y-m-d
        'discount_end_at' => ['type' => 'string', 'group' => 'discount'],   // Y-m-d
        'posts_require_approval' => ['type' => 'boolean', 'group' => 'posts'],
    ];

    /**
     * Get all site settings (flattened key => value)
     */
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->getSettingsData()]);
    }

    protected function getSettingsData(): array
    {
        $settings = SiteSetting::whereIn('key', array_keys($this->keys))->get()->keyBy('key');

        $data = [];
        foreach ($this->keys as $key => $config) {
            $setting = $settings->get($key);
            $value = $setting
                ? $this->castValue($setting->value, $config['type'])
                : $this->defaultValue($key);
            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * Update site settings
     */
    public function update(Request $request): JsonResponse
    {
        $rules = [
            'site_name' => 'sometimes|nullable|string|max:255',
            'site_logo' => 'sometimes|nullable|string|max:500',
            'place_status' => 'sometimes|nullable|string|in:open,closed',
            'maintenance_mode' => 'sometimes|boolean',
            'discount_percent' => 'sometimes|nullable|numeric|min:0|max:100',
            'discount_start_at' => 'sometimes|nullable|date',
            'discount_end_at' => 'sometimes|nullable|date|after_or_equal:discount_start_at',
            'posts_require_approval' => 'sometimes|boolean',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        foreach ($validator->validated() as $key => $value) {
            if (!array_key_exists($key, $this->keys)) {
                continue;
            }
            $config = $this->keys[$key];
            $serialized = $this->serializeValue($value, $config['type']);
            SiteSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $serialized, 'type' => $config['type'], 'group' => $config['group']]
            );
        }

        $data = $this->getSettingsData();

        return response()->json([
            'message' => 'Site settings updated successfully',
            'data' => $data,
        ]);
    }

    protected function castValue(?string $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'number' => (int) $value,
            'decimal', 'float' => (float) $value,
            default => $value,
        };
    }

    protected function serializeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($type === 'boolean') {
            return $value ? '1' : '0';
        }
        return (string) $value;
    }

    protected function defaultValue(string $key): mixed
    {
        return match ($key) {
            'site_name' => '',
            'site_logo' => null,
            'place_status' => 'open',
            'maintenance_mode' => false,
            'discount_percent' => null,
            'discount_start_at' => null,
            'discount_end_at' => null,
            'posts_require_approval' => false,
            default => null,
        };
    }
}
