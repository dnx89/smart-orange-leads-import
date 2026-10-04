<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The uploaded file: one request carries the whole CSV.
 */
class StoreImportRequest extends FormRequest
{
    /**
     * The largest file accepted, in bytes. PHP's own upload limits are set above it, so that a
     * bigger file is refused by this rule rather than dropped before the request reaches Laravel.
     */
    public const MAX_BYTES = 128 * 1024 * 1024;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:'.intdiv(self::MAX_BYTES, 1024),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Виберіть файл.',
            'file.mimes' => 'Підтримується лише файл .csv.',
            'file.max' => 'Файл завеликий: максимум '.intdiv(self::MAX_BYTES, 1024 * 1024).' МБ.',
        ];
    }
}
