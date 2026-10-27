<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreLeaveRequest;
use App\Models\LeaveRequest;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends ApiController
{
    public function __construct(private EmployeePortalService $portal) {}

    public function types(): JsonResponse
    {
        return response()->json($this->portal->leaveTypes());
    }

    public function balances(Request $request): JsonResponse
    {
        return response()->json($this->portal->leaveBalances($this->employee($request)));
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->portal->leaveRequests($this->employee($request)));
    }

    public function store(StoreLeaveRequest $request): JsonResponse
    {
        $data = $request->validated();

        $leave = $this->portal->requestLeave(
            $this->employee($request),
            isset($data['leave_type_id']) ? (int) $data['leave_type_id'] : null,
            $data['start_date'],
            $data['end_date'],
            $data['reason'] ?? null,
        );

        return response()->json($leave, 201);
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        return response()->json($this->portal->cancelLeave($this->employee($request), $leaveRequest));
    }
}
