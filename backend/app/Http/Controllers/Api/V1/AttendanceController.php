<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PunchRequest;
use App\Http\Requests\StoreManualRegularHoursRequest;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends ApiController
{
    public function __construct(private EmployeePortalService $portal) {}

    public function today(Request $request): JsonResponse
    {
        return response()->json($this->portal->today($this->employee($request)));
    }

    public function punchIn(PunchRequest $request): JsonResponse
    {
        return response()->json(
            ['record' => $this->portal->punchIn($this->employee($request), $request->meta('mobile'))],
            201
        );
    }

    public function punchOut(PunchRequest $request): JsonResponse
    {
        return response()->json(
            ['record' => $this->portal->punchOut($this->employee($request), $request->meta('mobile'))]
        );
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json($this->portal->attendanceHistory($this->employee($request), $data['month'] ?? null));
    }

    public function logManual(StoreManualRegularHoursRequest $request): JsonResponse
    {
        $data = $request->validated();

        $entry = $this->portal->logManualRegularHours(
            $this->employee($request), $data['work_date'], (int) $data['minutes'], $data['reason'] ?? null,
        );

        return response()->json($entry, 201);
    }

    public function totals(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json($this->portal->attendanceTotals($this->employee($request), $data['month'] ?? null));
    }

    public function dayEntry(Request $request): JsonResponse
    {
        $data = $request->validate(['work_date' => ['required', 'date']]);

        return response()->json($this->portal->dayEntry($this->employee($request), $data['work_date']));
    }

    public function calendar(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['required', 'date_format:Y-m']]);

        return response()->json($this->portal->attendanceCalendar($this->employee($request), $data['month']));
    }
}
