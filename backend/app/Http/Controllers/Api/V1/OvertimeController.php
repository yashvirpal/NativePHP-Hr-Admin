<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\OvertimeRateRequest;
use App\Http\Requests\StoreOvertimeRequest;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OvertimeController extends ApiController
{
    public function __construct(private EmployeePortalService $portal) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        return response()->json($this->portal->overtimeRequests($this->employee($request), $data['month'] ?? null));
    }

    public function store(StoreOvertimeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $overtime = $this->portal->requestOvertime(
            $this->employee($request),
            $data['work_date'],
            (int) $data['minutes'],
            $data['reason'] ?? null,
        );

        return response()->json($overtime, 201);
    }

    /** Current hourly rate plus its change history. */
    public function rate(Request $request): JsonResponse
    {
        return response()->json($this->portal->overtimeRate($this->employee($request)));
    }

    /** New rate applies to overtime submitted from now on; existing requests keep theirs. */
    public function updateRate(OvertimeRateRequest $request): JsonResponse
    {
        return response()->json(
            $this->portal->setOvertimeRate($this->employee($request), $request->validated('hourly_rate'))
        );
    }
}
