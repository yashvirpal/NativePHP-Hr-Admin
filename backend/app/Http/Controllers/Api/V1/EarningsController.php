<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EarningsController extends ApiController
{
    public function __construct(private EmployeePortalService $portal) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'months' => ['nullable', 'integer', 'in:1,3,6,12'],
            'from' => ['nullable', 'date', 'required_with:to'],
            'to' => ['nullable', 'date', 'required_with:from', 'after_or_equal:from', 'before_or_equal:today'],
        ]);

        if (! isset($data['from']) && ! isset($data['months'])) {
            throw ValidationException::withMessages(['months' => 'Provide months or a custom date range.']);
        }

        return response()->json($this->portal->earningsSummary(
            $this->employee($request),
            isset($data['months']) ? (int) $data['months'] : null,
            $data['from'] ?? null,
            $data['to'] ?? null,
        ));
    }
}
