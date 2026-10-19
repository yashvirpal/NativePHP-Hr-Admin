<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'device_info' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Server-controlled metadata: the client can never choose `source` or the punch time. */
    public function meta(string $source): array
    {
        return $this->validated() + ['source' => $source];
    }
}
