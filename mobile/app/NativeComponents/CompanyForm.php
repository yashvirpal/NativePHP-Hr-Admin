<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

/**
 * Add or edit an employer. Rule (enforced by the backend, mirrored here so the right fields show):
 * a current employer needs no end date; a past one needs both start and end dates.
 */
class CompanyForm extends Screen
{
    public ?int $companyId = null;

    public string $name = '';

    public string $industry = '';

    public string $designation = '';

    public string $website = '';

    public string $phone = '';

    public string $address = '';

    public string $startedOn = '';

    public string $endedOn = '';

    public bool $isCurrent = true;

    public function navTitle(): string
    {
        return $this->companyId ? __('Edit company') : __('Add company');
    }

    public function mount(): void
    {
        $company = $this->data('company');

        if (! is_array($company)) {
            return;
        }

        $this->companyId = (int) $company['id'];
        $this->name = (string) $company['name'];
        $this->industry = (string) ($company['industry'] ?? '');
        $this->designation = (string) ($company['designation'] ?? '');
        $this->website = (string) ($company['website'] ?? '');
        $this->phone = (string) ($company['phone'] ?? '');
        $this->address = (string) ($company['address'] ?? '');
        $this->startedOn = (string) ($company['started_on'] ?? '');
        $this->endedOn = (string) ($company['ended_on'] ?? '');
        $this->isCurrent = (bool) ($company['is_current'] ?? false);
    }

    public function save(): void
    {
        $payload = [
            'name' => trim($this->name),
            'industry' => $this->nullable($this->industry),
            'designation' => $this->nullable($this->designation),
            'website' => $this->nullable($this->website),
            'phone' => $this->nullable($this->phone),
            'address' => $this->nullable($this->address),
            'is_current' => $this->isCurrent,
            'started_on' => $this->nullable($this->startedOn),
            'ended_on' => $this->isCurrent ? null : $this->nullable($this->endedOn),
        ];

        $this->busy = true;
        $saved = $this->call(fn ($api) => $api->saveCompany($payload, $this->companyId));
        $this->busy = false;

        if ($saved !== null) {
            Dialog::toast(__('Company saved'), 'short');
            $this->back();
        }
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function render(): View
    {
        return view('native.company-form');
    }
}
