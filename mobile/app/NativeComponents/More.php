<?php

namespace App\NativeComponents;

use App\Services\LocalePreference;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/** Profile summary plus the screens that don't have their own tab. */
class More extends Screen
{
    public string $name = '';

    public string $email = '';

    /** @var list<array{label: string, value: string}> */
    public array $details = [];

    /**
     * Which option is highlighted, and the language every screen renders in. See the comment on
     * LocalePreference - Hindi is temporarily back on to test it on Android.
     */
    public string $locale = LocalePreference::DEFAULT;

    public function navTitle(): string
    {
        return __('More');
    }

    public function activeTab(): ?string
    {
        return '/more';
    }

    public function mount(): void
    {
        $this->locale = app(LocalePreference::class)->get();

        $me = $this->call(fn ($api) => $api->me());

        if ($me === null) {
            return;
        }

        $employee = $me['employee'] ?? [];

        $this->name = (string) ($me['name'] ?? '');
        $this->email = (string) ($me['email'] ?? '');
        $this->details = collect([
            [__('Employee code'), $employee['employee_code'] ?? null],
            [__('Designation'), $employee['designation'] ?? null],
            [__('Department'), $employee['department']['name'] ?? null],
            [__('Shift'), isset($employee['shift'])
                ? $employee['shift']['name'].' ('.substr($employee['shift']['start_time'], 0, 5).' - '.substr($employee['shift']['end_time'], 0, 5).')'
                : null],
            [__('Phone'), $employee['phone'] ?? null],
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

    public function setLanguage(string $locale): void
    {
        if (! in_array($locale, LocalePreference::SUPPORTED, true)) {
            return;
        }

        $this->locale = $locale;
        app(LocalePreference::class)->put($locale);
        App::setLocale($locale);
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
