<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Create roles and choose their permissions. Super admins only (gate: roles.manage). */
class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', ['roles' => Role::withCount('admins')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.roles.form', ['role' => new Role(['permissions' => []]), 'groups' => Permission::grouped()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $slug = Str::slug($data['name'], '_');
        if (Role::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['name' => 'A role with a very similar name already exists.']);
        }

        Role::create($data + ['slug' => $slug]);

        return redirect()->route('admin.roles.index')->with('status', 'Role created.');
    }

    public function edit(Role $role): View
    {
        $this->ensureEditable($role);

        return view('admin.roles.form', ['role' => $role, 'groups' => Permission::grouped()]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->ensureEditable($role);

        $role->update($this->validated($request, $role));

        return redirect()->route('admin.roles.index')->with('status', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        $this->ensureEditable($role);

        if ($role->admins()->exists()) {
            return back()->withErrors(['role' => "Reassign the admins who hold \"{$role->name}\" before deleting it."]);
        }

        $role->delete();

        return redirect()->route('admin.roles.index')->with('status', 'Role deleted.');
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::enum(Permission::class)],
        ]);

        $data['permissions'] = array_values(array_unique($data['permissions'] ?? []));

        return $data;
    }

    private function ensureEditable(Role $role): void
    {
        abort_if($role->is_system, 403, 'System roles are locked.');
    }
}
