<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OvertimeRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 0 is allowed (unpaid overtime); at most 2 decimal places.
            'hourly_rate' => ['required', 'numeric', 'min:0', 'max:99999.99', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'hourly_rate.decimal' => 'The hourly rate can have at most 2 decimal places.',
        ];
    }
}
