<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'sometimes', 'string', Rule::when($this->filled('password'), ['confirmed', Password::defaults()])],
            'phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'avatar' => ['nullable', $this->avatarRule()],
        ];
    }

    private const AVATAR_MAX_KB = 2048;
    private const AVATAR_MIMES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    private function avatarRule(): \Closure
    {
        $request = $this;
        $validateFile = function (mixed $file, string $attribute, \Closure $fail): bool {
            $validator = \Illuminate\Support\Facades\Validator::make(
                [$attribute => $file],
                [$attribute => ['image', 'mimes:jpeg,png,jpg,gif,webp', 'max:' . self::AVATAR_MAX_KB]],
                [
                    'avatar.image' => 'The avatar must be an image (jpeg, png, jpg, gif, or webp).',
                    'avatar.mimes' => 'The avatar must be a file of type: jpeg, png, jpg, gif, webp.',
                    'avatar.max' => 'The avatar may not be greater than 2 MB.',
                ]
            );
            if ($validator->fails()) {
                $fail($validator->errors()->first($attribute));
                return false;
            }
            return true;
        };

        $validateBase64 = function (string $data, string $attribute, \Closure $fail): bool {
            $decoded = base64_decode($data, true);
            if ($decoded === false || strlen($decoded) > self::AVATAR_MAX_KB * 1024) {
                $fail('The avatar may not be greater than 2 MB.');
                return false;
            }
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->buffer($decoded);
            if (!in_array($mime, self::AVATAR_MIMES, true)) {
                $fail('The avatar must be an image (jpeg, png, jpg, gif, or webp).');
                return false;
            }
            return true;
        };

        return function (string $attribute, mixed $value, \Closure $fail) use ($request, $validateFile, $validateBase64): void {
            if ($value === null || $value === '') {
                return;
            }
            if (is_array($value)) {
                if (empty($value)) {
                    return;
                }
                $file = $request->file('avatar') ?? reset($value);
                $isFile = $file && is_object($file) && method_exists($file, 'getClientOriginalName');
                if ($isFile && $validateFile($file, $attribute, $fail)) {
                    return;
                }
                $b64 = $value['data'] ?? $value['content'] ?? $value['base64'] ?? $value['file'] ?? $value['image'] ?? null;
                if (!is_string($b64)) {
                    $first = reset($value);
                    if (is_array($first)) {
                        $b64 = $first['data'] ?? $first['content'] ?? $first['base64'] ?? $first['file'] ?? $first['image'] ?? null;
                    } elseif (is_string($first)) {
                        $b64 = preg_match('/^data:image\/\w+;base64,(.+)$/s', $first, $m) ? $m[1] : $first;
                    }
                }
                if (!is_string($b64)) {
                    foreach ($value as $v) {
                        if (is_string($v) && strlen($v) > 100) {
                            $b64 = preg_match('/^data:image\/\w+;base64,(.+)$/s', $v, $m) ? $m[1] : $v;
                            break;
                        }
                    }
                }
                if (is_string($b64)) {
                    if (preg_match('/^data:image\/\w+;base64,(.+)$/s', $b64, $m)) {
                        $b64 = $m[1];
                    }
                    if ($validateBase64($b64, $attribute, $fail)) {
                        return;
                    }
                }
                $fail('Avatar must be: multipart file upload, JSON with base64 in avatar.data/content/base64, or preset path string.');
                return;
            }
            $isUploadedFile = $value instanceof UploadedFile
                || (is_object($value) && method_exists($value, 'getClientOriginalName') && method_exists($value, 'getRealPath'));
            if ($isUploadedFile) {
                $validateFile($value, $attribute, $fail);
                return;
            }
            if (is_string($value)) {
                if (in_array($value, User::PRESET_AVATARS, true)) {
                    return;
                }
                if (preg_match('/^data:image\/(\w+);base64,(.+)$/s', $value, $m)) {
                    if ($validateBase64($m[2], $attribute, $fail)) {
                        return;
                    }
                    return;
                }
                $fail('The selected avatar must be one of the available preset avatars.');
                return;
            }
            $fail('The avatar must be an image file, a base64 image, or a preset avatar path.');
        };
    }
}
