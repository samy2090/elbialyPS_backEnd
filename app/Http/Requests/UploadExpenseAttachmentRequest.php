<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadExpenseAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,txt,csv|max:51200', // 50MB = 51200KB
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File is required',
            'file.file' => 'Uploaded file is invalid',
            'file.mimes' => 'File must be one of: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG, TXT, CSV',
            'file.max' => 'File size must not exceed 50MB',
        ];
    }
}
