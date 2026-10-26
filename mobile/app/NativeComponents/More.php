<?php

namespace App\NativeComponents;

use Illuminate\View\View;

/** Profile summary plus the screens that don't have their own tab. */
class More extends Screen
{
    public string $name = '';

    public string $email = '';

    /** @var list<array{label: string, value: string}> */
    public array $details = [];

    public function navTitle(): string
    {
        return 'More';
    }

    public function activeTab(): ?string
    {
        return '/more';
    }

    public function mount(): void
    {
        $me = $this->call(fn ($api) => $api->me());

        if ($me === null) {
            return;
        }

        $employee = $me['employee'] ?? [];

        $this->name = (string) ($me['name'] ?? '');
        $this->email = (string) ($me['email'] ?? '');
        $this->details = collect([
            ['Employee code', $employee['employee_code'] ?? null],
            ['Designation', $employee['designation'] ?? null],
            ['Department', $employee['department']['name'] ?? null],
            ['Shift', isset($employee['shift'])
                ? $employee['shift']['name'].' ('.substr($employee['shift']['start_time'], 0, 5).' - '.substr($employee['shift']['end_time'], 0, 5).')'
                : null],
            ['Phone', $employee['phone'] ?? null],
        ])->filter(fn ($row) => filled($row[1]))
            ->map(fn ($row) => ['label' => $row[0], 'value' => (string) $row[1]])
            ->values()->all();
    }

    public function earning(): void
    {
        $this->navigate('/earning');
    }

    public function holidays(): void
    {
        $this->navigate('/holidays');
    }

    public function companies(): void
    {
        $this->navigate('/companies');
    }

    public function signOut(): void
    {
        $this->busy = true;
        $this->api()->logout();
        $this->busy = false;

        $this->replace('/login');
    }

    public function render(): View
    {
        return view('native.more');
    }
}
