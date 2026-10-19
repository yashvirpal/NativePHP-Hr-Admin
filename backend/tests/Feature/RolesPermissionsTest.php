<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Admin;
use App\Models\LeaveRequest;
use App\Models\Role;

class RolesPermissionsTest extends ApiTestCase
{
    // ─── Defaults from the migration ────────────────────────────────────────────

    public function test_migration_provides_super_admin_and_manager_roles(): void
    {
        $super = Role::where('slug', 'super_admin')->firstOrFail();
        $manager = Role::where('slug', 'manager')->firstOrFail();

        $this->assertTrue($super->is_system);
        $this->assertTrue($super->isSuperAdmin());
        $this->assertContains('leave.manage', $manager->permissions);
        $this->assertNotContains('admins.manage', $manager->permissions);
    }

    public function test_super_admin_holds_every_permission_including_future_ones(): void
    {
        $super = $this->makeAdmin('super_admin');

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($super->hasPermission($permission), $permission->value);
        }
        $this->assertTrue($super->hasPermission('something.added.later'));
        $this->assertTrue($super->can('roles.manage'));
    }

    public function test_an_admin_without_a_role_can_do_nothing_but_see_the_dashboard(): void
    {
        $admin = Admin::create(['name' => 'No Role', 'email' => 'norole@example.test', 'password' => 'password']);
        $this->actingAs($admin, 'admin');

        $this->get('/admin')->assertOk();
        $this->get('/admin/employees')->assertForbidden();
        $this->get('/admin/attendance')->assertForbidden();
    }

    // ─── Permissions are enforced per page and per action ───────────────────────

    public function test_a_role_only_opens_the_pages_it_was_granted(): void
    {
        $role = $this->makeRole([Permission::AttendanceView, Permission::EmployeesView]);
        $this->actingAs($this->makeAdmin($role), 'admin');

        $this->get('/admin')->assertOk();
        $this->get('/admin/attendance')->assertOk();
        $this->get('/admin/employees')->assertOk();

        $this->get('/admin/leave-requests')->assertForbidden();
        $this->get('/admin/overtime-requests')->assertForbidden();
        $this->get('/admin/holidays')->assertForbidden();
        $this->get('/admin/departments')->assertForbidden();
        $this->get('/admin/admins')->assertForbidden();
        $this->get('/admin/roles')->assertForbidden();
    }

    public function test_viewing_employees_does_not_allow_changing_them(): void
    {
        $this->actingAs($this->makeAdmin($this->makeRole([Permission::EmployeesView])), 'admin');

        $this->get('/admin/employees')->assertOk()->assertDontSee('Add employee');
        $this->get('/admin/employees/create')->assertForbidden();
        $this->post('/admin/employees', [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'password123', 'employee_code' => 'X1', 'status' => 'active',
        ])->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'x@example.com']);
    }

    public function test_deciding_leave_needs_leave_permission_not_overtime_permission(): void
    {
        $leave = LeaveRequest::create([
            'employee_id' => $this->makeUser()->employee->id, 'leave_type_id' => $this->leaveType()->id,
            'start_date' => '2026-11-02', 'end_date' => '2026-11-02', 'days' => 1, 'status' => 'pending',
        ]);

        $this->actingAs($this->makeAdmin($this->makeRole([Permission::OvertimeManage])), 'admin')
            ->post("/admin/leave-requests/{$leave->id}/approve")->assertForbidden();
        $this->assertSame('pending', $leave->fresh()->status);

        $this->actingAs($this->makeAdmin($this->makeRole([Permission::LeaveManage])), 'admin')
            ->post("/admin/leave-requests/{$leave->id}/approve")->assertRedirect();
        $this->assertSame('approved', $leave->fresh()->status);
    }

    public function test_sidebar_only_lists_what_the_role_allows(): void
    {
        $this->actingAs($this->makeAdmin($this->makeRole([Permission::AttendanceView])), 'admin');

        $this->get('/admin')->assertOk()
            ->assertSee('/admin/attendance', false)
            ->assertDontSee('/admin/leave-requests', false)
            ->assertDontSee('/admin/roles', false)
            ->assertDontSee('/admin/admins', false);
    }

    public function test_an_employee_never_passes_a_permission_gate(): void
    {
        $user = $this->makeUser();

        foreach (Permission::cases() as $permission) {
            $this->assertFalse($user->can($permission->value), $permission->value);
        }
        $this->assertFalse($user->can('roles.manage'));
    }

    // ─── Managing roles (super admin only) ──────────────────────────────────────

    public function test_only_super_admin_can_manage_roles_even_with_admins_manage(): void
    {
        $delegate = $this->makeAdmin($this->makeRole([Permission::AdminsManage]));
        $this->actingAs($delegate, 'admin');

        $this->get('/admin/admins')->assertOk();
        $this->get('/admin/roles')->assertForbidden();
        $this->post('/admin/roles', ['name' => 'Sneaky', 'permissions' => Permission::values()])->assertForbidden();
        $this->assertDatabaseMissing('roles', ['name' => 'Sneaky']);
    }

    public function test_super_admin_creates_edits_and_deletes_a_role(): void
    {
        $this->actingAs($this->makeAdmin('super_admin'), 'admin');

        $this->post('/admin/roles', [
            'name' => 'Recruiter', 'description' => 'Hiring', 'permissions' => ['employees.view', 'employees.manage'],
        ])->assertRedirect('/admin/roles');

        $role = Role::where('name', 'Recruiter')->firstOrFail();
        $this->assertSame('recruiter', $role->slug);
        $this->assertSame(['employees.view', 'employees.manage'], $role->permissions);

        $this->put("/admin/roles/{$role->id}", ['name' => 'Recruiter', 'permissions' => ['attendance.view']])
            ->assertRedirect('/admin/roles');
        $this->assertSame(['attendance.view'], $role->fresh()->permissions);

        $this->delete("/admin/roles/{$role->id}")->assertRedirect('/admin/roles');
        $this->assertModelMissing($role);
    }

    public function test_unknown_permissions_are_rejected(): void
    {
        $this->actingAs($this->makeAdmin('super_admin'), 'admin');

        $this->post('/admin/roles', ['name' => 'Bad', 'permissions' => ['employees.view', 'launch.rockets']])
            ->assertSessionHasErrors('permissions.1');
        $this->assertDatabaseMissing('roles', ['name' => 'Bad']);
    }

    public function test_system_role_is_locked(): void
    {
        $this->actingAs($this->makeAdmin('super_admin'), 'admin');
        $super = Role::where('slug', 'super_admin')->firstOrFail();

        $this->get("/admin/roles/{$super->id}/edit")->assertForbidden();
        $this->put("/admin/roles/{$super->id}", ['name' => 'Renamed', 'permissions' => []])->assertForbidden();
        $this->delete("/admin/roles/{$super->id}")->assertForbidden();
        $this->assertSame('Super admin', $super->fresh()->name);
    }

    public function test_a_role_in_use_cannot_be_deleted(): void
    {
        $role = $this->makeRole([Permission::AttendanceView]);
        $this->makeAdmin($role);
        $this->actingAs($this->makeAdmin('super_admin'), 'admin');

        $this->delete("/admin/roles/{$role->id}")->assertSessionHasErrors('role');
        $this->assertModelExists($role);
    }

    public function test_role_names_must_be_unique_including_similar_slugs(): void
    {
        $this->actingAs($this->makeAdmin('super_admin'), 'admin');
        Role::create(['name' => 'HR Lead', 'slug' => 'hr_lead', 'permissions' => []]);

        $this->post('/admin/roles', ['name' => 'HR Lead'])->assertSessionHasErrors('name');
        $this->post('/admin/roles', ['name' => 'HR-Lead'])->assertSessionHasErrors('name'); // same slug
        $this->assertSame(1, Role::where('slug', 'hr_lead')->count());
    }

    // ─── Assigning roles to admins, without privilege escalation ────────────────

    public function test_super_admin_assigns_any_role(): void
    {
        $role = $this->makeRole([Permission::AttendanceView]);
        $this->actingAs($this->makeAdmin('super_admin'), 'admin');

        $this->post('/admin/admins', [
            'name' => 'New Admin', 'email' => 'new.admin@example.test', 'password' => 'password123', 'role_id' => $role->id,
        ])->assertRedirect('/admin/admins');

        $created = Admin::where('email', 'new.admin@example.test')->firstOrFail();
        $this->assertSame($role->id, $created->role_id);
        $this->assertNotSame('password123', $created->password, 'password must be hashed');
    }

    public function test_admins_manager_cannot_hand_out_the_super_admin_role(): void
    {
        $this->actingAs($this->makeAdmin($this->makeRole([Permission::AdminsManage])), 'admin');
        $super = Role::where('slug', 'super_admin')->firstOrFail();

        $this->post('/admin/admins', [
            'name' => 'Evil', 'email' => 'evil@example.test', 'password' => 'password123', 'role_id' => $super->id,
        ])->assertForbidden();
        $this->assertDatabaseMissing('admins', ['email' => 'evil@example.test']);
    }

    public function test_admins_manager_cannot_grant_more_access_than_they_hold(): void
    {
        $actor = $this->makeAdmin($this->makeRole([Permission::AdminsManage, Permission::AttendanceView]));
        $bigger = $this->makeRole([Permission::AttendanceView, Permission::LeaveManage]);
        $subset = $this->makeRole([Permission::AttendanceView]);
        $this->actingAs($actor, 'admin');

        $this->post('/admin/admins', [
            'name' => 'A', 'email' => 'a@example.test', 'password' => 'password123', 'role_id' => $bigger->id,
        ])->assertForbidden();

        $this->post('/admin/admins', [
            'name' => 'B', 'email' => 'b@example.test', 'password' => 'password123', 'role_id' => $subset->id,
        ])->assertRedirect('/admin/admins');
    }

    public function test_admins_manager_cannot_edit_or_promote_themselves_into_super_admin(): void
    {
        $actor = $this->makeAdmin($this->makeRole([Permission::AdminsManage]));
        $super = Role::where('slug', 'super_admin')->firstOrFail();
        $this->actingAs($actor, 'admin');

        $this->put("/admin/admins/{$actor->id}", [
            'name' => $actor->name, 'email' => $actor->email, 'role_id' => $super->id,
        ])->assertForbidden();
        $this->assertFalse($actor->fresh()->isSuperAdmin());
    }

    public function test_admins_manager_cannot_modify_a_super_admin(): void
    {
        $target = $this->makeAdmin('super_admin');
        $this->actingAs($this->makeAdmin($this->makeRole([Permission::AdminsManage])), 'admin');

        $this->get("/admin/admins/{$target->id}/edit")->assertForbidden();
        $this->put("/admin/admins/{$target->id}", [
            'name' => 'Hijacked', 'email' => $target->email, 'password' => 'newpassword1', 'role_id' => $target->role_id,
        ])->assertForbidden();
        $this->assertNotSame('Hijacked', $target->fresh()->name);
    }

    public function test_the_last_super_admin_cannot_be_demoted(): void
    {
        $only = $this->makeAdmin('super_admin');
        $manager = Role::where('slug', 'manager')->firstOrFail();

        $this->actingAs($only, 'admin')->put("/admin/admins/{$only->id}", [
            'name' => $only->name, 'email' => $only->email, 'role_id' => $manager->id,
        ])->assertSessionHasErrors('role_id');

        $this->assertTrue($only->fresh()->isSuperAdmin());
    }

    public function test_a_super_admin_can_be_demoted_when_another_exists(): void
    {
        $first = $this->makeAdmin('super_admin');
        $this->makeAdmin('super_admin');
        $manager = Role::where('slug', 'manager')->firstOrFail();

        $this->actingAs($first, 'admin')->put("/admin/admins/{$first->id}", [
            'name' => $first->name, 'email' => $first->email, 'role_id' => $manager->id,
        ])->assertRedirect('/admin/admins');

        $this->assertFalse($first->fresh()->isSuperAdmin());
    }

    public function test_role_changes_apply_immediately_to_signed_in_admins(): void
    {
        $role = $this->makeRole([Permission::AttendanceView]);
        $admin = $this->makeAdmin($role);
        $this->actingAs($admin, 'admin')->get('/admin/attendance')->assertOk();

        $role->update(['permissions' => []]);

        $this->actingAs(Admin::find($admin->id), 'admin')->get('/admin/attendance')->assertForbidden();
    }
}
