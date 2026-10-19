<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use App\Services\EmployeePortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends ApiController
{
    public function __construct(private EmployeePortalService $portal) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->portal->companies($this->employee($request)));
    }

    public function store(CompanyRequest $request): JsonResponse
    {
        return response()->json($this->portal->addCompany($this->employee($request), $request->company()), 201);
    }

    public function update(CompanyRequest $request, Company $company): JsonResponse
    {
        return response()->json($this->portal->updateCompany($this->employee($request), $company, $request->company()));
    }

    public function destroy(Request $request, Company $company): JsonResponse
    {
        $this->portal->deleteCompany($this->employee($request), $company);

        return response()->json(null, 204);
    }
}
