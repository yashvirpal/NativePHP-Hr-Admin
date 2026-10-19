<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends ApiController
{
    public function __construct(private EmployeePortalService $portal) {}

    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($this->portal->dashboard($this->employee($request)));
    }
}
