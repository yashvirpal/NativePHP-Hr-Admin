<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreManualRegularHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_date' => ['required', 'date', 'before_or_equal:today'],
            'minutes' => ['required', 'integer', 'between:1,1440'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
