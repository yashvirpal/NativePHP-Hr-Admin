<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    /** Updating: the company must be the caller's own. Runs before validation, so a stranger gets 403, not 422. */
    public function authorize(): bool
    {
        $company = $this->route('company');

        if (! $company instanceof Company) {
            return true; // creating
        }

        $employeeId = $this->user()?->employee?->id;

        return $employeeId !== null && $employeeId === $company->employee_id;
    }

    /** An unticked checkbox is simply absent from a form post; treat that as "not current". */
    protected function prepareForValidation(): void
    {
        $this->merge(['is_current' => $this->boolean('is_current')]);
    }

    public function rules(): array
    {
        $current = $this->boolean('is_current');

        return [
            'name' => ['required', 'string', 'max:255'],
            'industry' => ['nullable', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_current' => ['boolean'],

            // Someone you no longer work for needs the period you worked there.
            'started_on' => [Rule::requiredIf(! $current), 'nullable', 'date', 'before_or_equal:today'],
            'ended_on' => [
                Rule::requiredIf(! $current), 'nullable', 'date',
                'after_or_equal:started_on', 'before_or_equal:today',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'started_on.required' => 'Enter the date you started, since this is not your current employer.',
            'ended_on.required' => 'Enter the date you left, since this is not your current employer.',
            'ended_on.after_or_equal' => 'The end date cannot be before the start date.',
        ];
    }

    /** Validated data, with the end date cleared for a current employer. */
    public function company(): array
    {
        $data = $this->validated();

        if ($data['is_current']) {
            $data['ended_on'] = null;
        }

        return $data;
    }
}
