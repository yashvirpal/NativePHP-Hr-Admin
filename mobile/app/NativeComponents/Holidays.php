<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;

class Holidays extends Screen
{
    public int $year = 0;

    /** @var list<array{name: string, date: string, optional: bool}> */
    public array $holidays = [];

    public function navTitle(): string
    {
        return 'Holidays';
    }

    public function mount(): void
    {
        $this->year = (int) now()->year;
        $this->load();
    }

    public function refresh(): void
    {
        $this->load();
    }

    public function previousYear(): void
    {
        $this->year--;
        $this->load();
    }

    public function nextYear(): void
    {
        $this->year++;
        $this->load();
    }

    private function load(): void
    {
        $holidays = $this->call(fn ($api) => $api->holidays($this->year));

        if ($holidays === null) {
            return;
        }

        $this->holidays = collect($holidays)->map(fn ($h) => [
            'name' => (string) $h['name'],
            'date' => Format::date($h['date'] ?? null, 'D, d M Y'),
            'optional' => (bool) ($h['is_optional'] ?? false),
        ])->all();
    }

    public function render(): View
    {
        return view('native.holidays');
    }
}
