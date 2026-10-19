<?php

namespace App\Http\Controllers\Employee;

use App\Http\Requests\CompanyRequest;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** An employee's own employer details. */
class CompanyController extends PortalController
{
    public function index(Request $request): View
    {
        return view('employee.companies.index', [
            'companies' => $this->portal->companies($this->employee($request)),
        ]);
    }

    public function create(): View
    {
        return view('employee.companies.form', ['company' => new Company(['is_current' => true])]);
    }

    public function store(CompanyRequest $request): RedirectResponse
    {
        $this->portal->addCompany($this->employee($request), $request->company());

        return redirect()->route('employee.companies.index')->with('status', 'Company added.');
    }

    public function edit(Request $request, Company $company): View
    {
        abort_unless($company->employee_id === $this->employee($request)->id, 403);

        return view('employee.companies.form', ['company' => $company]);
    }

    public function update(CompanyRequest $request, Company $company): RedirectResponse
    {
        $this->portal->updateCompany($this->employee($request), $company, $request->company());

        return redirect()->route('employee.companies.index')->with('status', 'Company updated.');
    }

    public function destroy(Request $request, Company $company): RedirectResponse
    {
        $this->portal->deleteCompany($this->employee($request), $company);

        return redirect()->route('employee.companies.index')->with('status', 'Company removed.');
    }
}
