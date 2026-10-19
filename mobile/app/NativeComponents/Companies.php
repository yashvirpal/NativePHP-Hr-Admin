<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

/** The employee's employer details (companies they work or worked for). */
class Companies extends Screen
{
    /** @var list<array<string, mixed>> */
    public array $companies = [];

    public function navTitle(): string
    {
        return 'My companies';
    }

    public function mount(): void
    {
        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    public function refresh(): void
    {
        $this->load();
    }

    public function add(): void
    {
        $this->navigate('/companies/form');
    }

    public function edit(int $id): void
    {
        $company = collect($this->companies)->firstWhere('id', $id);

        if ($company !== null) {
            $this->navigate('/companies/form', ['company' => $company]);
        }
    }

    public function confirmRemove(int $id): void
    {
        Dialog::alert('Remove company?', 'It will be removed from your profile.', [
            ['label' => 'Keep it', 'style' => 'cancel'],
            ['label' => 'Remove', 'style' => 'destructive'],
        ])->buttonPressed(function ($event) use ($id) {
            if ($event->label === 'Remove') {
                $this->remove($id);
            }
        });
    }

    public function remove(int $id): void
    {
        $this->busy = true;
        $this->call(fn ($api) => $api->deleteCompany($id));
        $this->busy = false;

        if ($this->error === '') {
            Dialog::toast('Company removed', 'short');
            $this->load();
        }
    }

    private function load(): void
    {
        $companies = $this->call(fn ($api) => $api->companies());

        if ($companies === null) {
            return;
        }

        $this->companies = collect($companies)->map(function ($c) {
            $current = (bool) ($c['is_current'] ?? false);

            return [
                'id' => (int) $c['id'],
                'name' => (string) $c['name'],
                'industry' => (string) ($c['industry'] ?? ''),
                'designation' => (string) ($c['designation'] ?? ''),
                'website' => (string) ($c['website'] ?? ''),
                'phone' => (string) ($c['phone'] ?? ''),
                'address' => (string) ($c['address'] ?? ''),
                'started_on' => (string) ($c['started_on'] ?? ''),
                'ended_on' => (string) ($c['ended_on'] ?? ''),
                'is_current' => $current,
                'period' => ($c['started_on'] ? Format::date($c['started_on'], 'M Y') : '?')
                    .' - '.($current ? 'Present' : ($c['ended_on'] ? Format::date($c['ended_on'], 'M Y') : '?')),
            ];
        })->all();
    }

    public function render(): View
    {
        return view('native.companies');
    }
}
