<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;

class Leave extends Screen
{
    /** @var list<array{type: string, period: string, days: string, reason: string}> */
    public array $requests = [];

    public function navTitle(): string
    {
        return 'Leave';
    }

    public function activeTab(): ?string
    {
        return '/leave';
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

    private function load(): void
    {
        $page = $this->call(fn ($api) => $api->leaveRequests());
        if ($page === null) {
            return;
        }

        $this->requests = collect($page['data'] ?? [])->map(function ($r) {
            $same = ($r['start_date'] ?? null) === ($r['end_date'] ?? null);

            return [
                'type' => (string) ($r['leave_type']['name'] ?? 'Leave'),
                'period' => $same
                    ? Format::date($r['start_date'], 'd M Y')
                    : Format::date($r['start_date'], 'd M').' - '.Format::date($r['end_date'], 'd M Y'),
                'days' => Format::days($r['days'] ?? 0),
                'reason' => (string) ($r['reason'] ?? ''),
            ];
        })->all();
    }

    public function render(): View
    {
        return view('native.leave');
    }
}
