<?php

namespace App\NativeComponents;

use App\Support\Format;
use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

class Leave extends Screen
{
    /** @var list<array{name: string, remaining: string, allocated: string}> */
    public array $balances = [];

    /** @var list<array{id: int, type: string, period: string, days: string, status: string, statusKey: string, reason: string, canCancel: bool}> */
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

    /** Fires when returning from the "new request" screen, so the new request appears. */
    public function onResume(): void
    {
        $this->load();
    }

    public function refresh(): void
    {
        $this->load();
    }

    public function newRequest(): void
    {
        $this->navigate('/leave/new');
    }

    /** Asks before cancelling; the actual call is cancel(). */
    public function confirmCancel(int $id): void
    {
        Dialog::alert('Cancel request?', 'This leave request will be withdrawn.', [
            ['label' => 'Keep it', 'style' => 'cancel'],
            ['label' => 'Cancel request', 'style' => 'destructive'],
        ])->buttonPressed(function ($event) use ($id) {
            if ($event->label === 'Cancel request') {
                $this->cancel($id);
            }
        });
    }

    public function cancel(int $id): void
    {
        $this->busy = true;
        $result = $this->call(fn ($api) => $api->cancelLeave($id));
        $this->busy = false;

        if ($result !== null) {
            Dialog::toast('Request cancelled', 'short');
            $this->load();
        }
    }

    private function load(): void
    {
        $balances = $this->call(fn ($api) => $api->leaveBalances());
        if ($balances === null) {
            return;
        }

        $this->balances = collect($balances)
            ->filter(fn ($b) => (float) ($b['allocated'] ?? 0) > 0)
            ->map(fn ($b) => [
                'name' => (string) ($b['leave_type']['name'] ?? ''),
                'remaining' => Format::days($b['remaining'] ?? 0),
                'allocated' => Format::days($b['allocated'] ?? 0),
            ])->values()->all();

        $page = $this->call(fn ($api) => $api->leaveRequests());
        if ($page === null) {
            return;
        }

        $this->requests = collect($page['data'] ?? [])->map(function ($r) {
            $same = ($r['start_date'] ?? null) === ($r['end_date'] ?? null);

            return [
                'id' => (int) $r['id'],
                'type' => (string) ($r['leave_type']['name'] ?? 'Leave'),
                'period' => $same
                    ? Format::date($r['start_date'], 'd M Y')
                    : Format::date($r['start_date'], 'd M').' - '.Format::date($r['end_date'], 'd M Y'),
                'days' => Format::days($r['days'] ?? 0),
                'status' => Format::status($r['status'] ?? null),
                'statusKey' => (string) ($r['status'] ?? ''),
                'reason' => (string) ($r['reason'] ?? ''),
                'canCancel' => ($r['status'] ?? '') === 'pending',
            ];
        })->all();
    }

    public function render(): View
    {
        return view('native.leave');
    }
}
