<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Creates employee logins (the `users` table) plus their employment profile. */
class EmployeeController extends Controller
{
    public function index(): View
    {
        return view('admin.employees.index', [
            'employees' => Employee::with('user', 'department')->orderBy('employee_code')->paginate(25),
        ]);
    }

    public function create(): View
    {
        return view('admin.employees.form', $this->formData(new Employee(['status' => 'active'])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->employeeRules(),
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        DB::transaction(function () use ($data) {
            $user = User::create(collect($data)->only('name', 'email', 'password')->all());

            Employee::create(['user_id' => $user->id] + $this->profileFields($data));
        });

        return redirect()->route('admin.employees.index')->with('status', 'Employee created.');
    }

    public function edit(Employee $employee): View
    {
        return view('admin.employees.form', $this->formData($employee->load('user')));
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            ...$this->employeeRules($employee),
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($employee->user_id)],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        DB::transaction(function () use ($data, $employee) {
            $user = $employee->user;
            $user->fill(['name' => $data['name'], 'email' => $data['email']]);
            if (! empty($data['password'])) {
                $user->password = $data['password'];
            }
            $user->save();

            $employee->update($this->profileFields($data));
        });

        return redirect()->route('admin.employees.index')->with('status', 'Employee updated.');
    }

    private function profileFields(array $data): array
    {
        return collect($data)->only(
            'department_id', 'shift_id', 'manager_id', 'employee_code', 'designation', 'phone', 'joined_on', 'status'
        )->all();
    }

    private function employeeRules(?Employee $employee = null): array
    {
        return [
            'employee_code' => ['required', 'string', 'max:50', Rule::unique('employees', 'employee_code')->ignore($employee?->id)],
            'department_id' => ['nullable', 'exists:departments,id'],
            'shift_id' => ['nullable', 'exists:shifts,id'],
            'manager_id' => ['nullable', 'exists:employees,id', Rule::notIn([$employee?->id])],
            'designation' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'joined_on' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    private function formData(Employee $employee): array
    {
        return [
            'employee' => $employee,
            'departments' => Department::orderBy('name')->get(),
            'shifts' => Shift::orderBy('name')->get(),
            'managers' => Employee::with('user')->where('status', 'active')->get(),
        ];
    }
}
