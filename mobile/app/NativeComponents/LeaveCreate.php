<?php

namespace App\NativeComponents;

use Illuminate\View\View;
use Native\Mobile\Facades\Dialog;

class LeaveCreate extends Screen
{
    /** Name of the chosen leave type (the select works with display names). */
    public string $typeName = '';

    public string $startDate = '';

    public string $endDate = '';

    public string $reason = '';

    /** @var list<array{id: int, name: string}> */
    public array $types = [];

    public function navTitle(): string
    {
        return 'New leave request';
    }

    public function mount(): void
    {
        $this->startDate = $this->endDate = now()->toDateString();

        $types = $this->call(fn ($api) => $api->leaveTypes());

        $this->types = collect($types ?? [])
            ->map(fn ($t) => ['id' => (int) $t['id'], 'name' => (string) $t['name']])
            ->all();
    }

    /** @return list<string> */
    public function typeNames(): array
    {
        return array_column($this->types, 'name');
    }

    public function submit(): void
    {
        $type = collect($this->types)->firstWhere('name', $this->typeName);

        if ($type === null) {
            $this->error = '';
            $this->fieldErrors = ['leave_type_id' => 'Choose a leave type.'];

            return;
        }

        $this->busy = true;
        $created = $this->call(fn ($api) => $api->requestLeave(
            $type['id'], trim($this->startDate), trim($this->endDate), trim($this->reason) ?: null,
        ));
        $this->busy = false;

        if ($created !== null) {
            Dialog::toast('Leave request submitted', 'short');
            $this->back();
        }
    }

    public function render(): View
    {
        return view('native.leave-create');
    }
}
