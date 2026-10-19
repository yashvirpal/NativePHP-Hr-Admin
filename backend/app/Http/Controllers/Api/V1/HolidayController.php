<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayController extends ApiController
{
    public function __construct(private EmployeePortalService $portal) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['year' => ['nullable', 'integer', 'between:2000,2100']]);

        return response()->json($this->portal->holidays(isset($data['year']) ? (int) $data['year'] : null));
    }
}
