<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

class Home extends Screen
{
    public string $name = '';

    public string $subtitle = '';

    public bool $loaded = false;

    public bool $punchedIn = false;

    public bool $finishedToday = false;

    /** "Punched in since 09:02" etc. */
    public string $todayLine = 'Not punched in';

    public string $todayStatus = '';

    /** @var array{present: int, late: int, worked: string} */
    public array $month = ['present' => 0, 'late' => 0, 'worked' => '-'];

    /** @var list<array{name: string, remaining: string, allocated: string}> */
    public array $balances = [];

    /** @var list<array{name: string, date: string, optional: bool}> */
    public array $holidays = [];

    public function navTitle(): string
    {
        return 'Home';
    }

    public function activeTab(): ?string
    {
        return '/home';
    }

    public function mount(): void
    {
        $this->load();
    }

    public function onResume(): void
    {
        $this->load();
    }

    /** Pull to refresh. */
    public function refresh(): void
    {
        $this->load();
    }

    public function punchIn(): void
    {
        $this->punch(fn ($api) => $api->punchIn(['device_info' => config('hr.device_name')]), 'Punched in');
    }

    public function punchOut(): void
    {
        $this->punch(fn ($api) => $api->punchOut(['device_info' => config('hr.device_name')]), 'Punched out');
    }

    private function punch(\Closure $request, string $message): void
    {
        $this->busy = true;
        $result = $this->call($request);
        $this->busy = false;

        if ($result !== null) {
            Dialog::toast($message, 'short');
            $this->load();
        }
    }

    private function load(): void
    {
        $data = $this->call(fn ($api) => $api->dashboard());

        if ($data === null) {
            return;
        }

        $employee = $data['employee'] ?? [];
        $today = $data['today'] ?? null;

        $this->loaded = true;
        $this->name = (string) ($employee['user']['name'] ?? '');
        $this->subtitle = collect([$employee['designation'] ?? null, $employee['department']['name'] ?? null])->filter()->implode(' - ');

        $this->punchedIn = (bool) ($data['is_punched_in'] ?? false);
        $this->finishedToday = ! $this->punchedIn && ($today['last_out_at'] ?? null) !== null;
        $this->todayStatus = Format::status($today['status'] ?? null);
        $this->todayLine = match (true) {
            $this->punchedIn => 'Punched in since '.Format::time($today['first_in_at'] ?? null),
            $this->finishedToday => 'Last out at '.Format::time($today['last_out_at']).' - '.Format::minutes($today['worked_minutes'] ?? 0).' worked',
            default => 'Not punched in',
        };

        $month = $data['month'] ?? [];
        $this->month = [
            'present' => (int) ($month['days_present'] ?? 0),
            'late' => (int) ($month['days_late'] ?? 0),
            'worked' => Format::minutes($month['worked_minutes'] ?? 0),
        ];

        $this->balances = collect($data['balances'] ?? [])
            ->filter(fn ($b) => (float) ($b['allocated'] ?? 0) > 0)
            ->map(fn ($b) => [
                'name' => (string) ($b['leave_type']['name'] ?? ''),
                'remaining' => Format::days($b['remaining'] ?? 0),
                'allocated' => Format::days($b['allocated'] ?? 0),
            ])->values()->all();

        $this->holidays = collect($data['upcoming_holidays'] ?? [])->map(fn ($h) => [
            'name' => (string) $h['name'],
            'date' => Format::date($h['date'] ?? null, 'D, d M'),
            'optional' => (bool) ($h['is_optional'] ?? false),
        ])->all();
    }

    public function render(): View
    {
        return view('native.home');
    }
}
