<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Manage back-office accounts (needs the admins.manage permission).
 *
 * No privilege escalation: you can only hand out roles whose permissions you already hold,
 * and only a super admin can create, edit or demote another super admin.
 */
class AdminController extends Controller
{
    public function index(): View
    {
        return view('admin.admins.index', ['admins' => Admin::with('role')->orderBy('name')->paginate(25)]);
    }

    public function create(Request $request): View
    {
        return view('admin.admins.form', [
            'admin' => new Admin,
            'roles' => $this->assignableRoles($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:admins,email'],
            'password' => ['required', 'string', 'min:8'],
            'role_id' => ['required', Rule::exists('roles', 'id')],
        ]);

        $this->authorizeGrant($request->user(), Role::findOrFail($data['role_id']));

        Admin::create($data);

        return redirect()->route('admin.admins.index')->with('status', 'Admin created.');
    }

    public function edit(Request $request, Admin $admin): View
    {
        $this->authorizeTarget($request->user(), $admin);

        return view('admin.admins.form', [
            'admin' => $admin,
            'roles' => $this->assignableRoles($request->user(), $admin->role),
        ]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $actor = $request->user();
        $this->authorizeTarget($actor, $admin);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')->ignore($admin->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role_id' => ['required', Rule::exists('roles', 'id')],
        ]);

        $role = Role::findOrFail($data['role_id']);

        // Re-saving an unchanged role is always fine; changing it is a grant and must be allowed.
        if ($role->id !== $admin->role_id) {
            $this->authorizeGrant($actor, $role);
        }

        // Never leave the system without a super admin.
        if ($admin->isSuperAdmin() && ! $role->isSuperAdmin()
            && Admin::whereHas('role', fn ($q) => $q->where('slug', Role::SUPER_ADMIN))->count() <= 1) {
            throw ValidationException::withMessages(['role_id' => 'There must be at least one super admin.']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $admin->update($data);

        return redirect()->route('admin.admins.index')->with('status', 'Admin updated.');
    }

    /** Roles this actor may assign. The role an admin already has is kept in the list so the form can show it. */
    private function assignableRoles(Admin $actor, ?Role $current = null): Collection
    {
        return Role::orderBy('name')->get()->filter(
            fn (Role $role) => $role->id === $current?->id || $this->mayGrant($actor, $role)
        )->values();
    }

    private function mayGrant(Admin $actor, Role $role): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        return ! $role->isSuperAdmin()
            && array_diff($role->permissions ?? [], $actor->role?->permissions ?? []) === [];
    }

    private function authorizeGrant(Admin $actor, Role $role): void
    {
        abort_unless($this->mayGrant($actor, $role), 403, 'You cannot assign a role with more access than your own.');
    }

    /** Only super admins may touch super admin accounts. */
    private function authorizeTarget(Admin $actor, Admin $target): void
    {
        abort_if($target->isSuperAdmin() && ! $actor->isSuperAdmin(), 403, 'Only a super admin can modify a super admin.');
    }
}
