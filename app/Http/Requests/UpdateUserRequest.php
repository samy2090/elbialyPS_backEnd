<?php

namespace App\Http\Requests;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'sometimes', 'string', Rule::when($this->filled('password'), ['confirmed', Password::defaults()])],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'status' => ['sometimes', 'required', Rule::in(UserStatus::values())],
            'avatar' => ['nullable', $this->avatarRule()],
        ];
    }

    /**
     * Avatar: either an uploaded image file or a preset path string.
     */
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
            // Array: empty = no avatar (pass); or multipart file bag / JSON base64
            if (is_array($value)) {
                if (empty($value)) {
                    return; // avatar: [] = no avatar, same as nullable
                }
                $file = $request->file('avatar') ?? reset($value);
                $isFile = $file && is_object($file) && method_exists($file, 'getClientOriginalName');
                if ($isFile && $validateFile($file, $attribute, $fail)) {
                    return;
                }
                // Base64: try common keys, then first element (object or string), then any string in array
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
                $keys = array_keys($value);
                $fail(
                    'Avatar must be: (1) multipart file upload, (2) JSON with base64 in avatar.data/content/base64, or (3) preset path string. '
                    . 'Received array with keys: ' . implode(', ', $keys) . '.'
                );
                return;
            }
            // UploadedFile (multipart)
            $isUploadedFile = $value instanceof UploadedFile
                || (is_object($value) && method_exists($value, 'getClientOriginalName') && method_exists($value, 'getRealPath'));
            if ($isUploadedFile) {
                $validateFile($value, $attribute, $fail);
                return;
            }
            // String: preset path or data URL
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

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'username.required' => 'The username field is required.',
            'username.unique' => 'This username is already taken.',
            'email.required' => 'The email field is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.confirmed' => 'The password confirmation does not match.',
            'phone.required' => 'The phone field is required.',
            'role_id.required' => 'The role field is required.',
            'role_id.exists' => 'The selected role is invalid.',
            'status.required' => 'The status field is required.',
            'status.in' => 'The selected status is invalid.',
            'avatar.image' => 'The avatar must be an image (jpeg, png, jpg, gif, or webp).',
            'avatar.mimes' => 'The avatar must be a file of type: jpeg, png, jpg, gif, webp.',
            'avatar.max' => 'The avatar may not be greater than 2 MB.',
            'avatar.in' => 'The selected avatar must be one of the available preset avatars.',
        ];
    }
}