<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the fixed `admins.role` string (super_admin | manager) with real roles that carry permissions.
 * Existing admins keep their access: super_admin -> "Super admin", manager -> "Manager".
 * (Permission strings are literal on purpose: a migration must not depend on app code that may change.)
 */
return new class extends Migration
{
    private array $managerPermissions = [
        'employees.view', 'employees.manage', 'departments.manage',
        'attendance.view', 'leave.manage', 'overtime.manage', 'holidays.manage',
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false); // locked: cannot be edited or deleted
            $table->timestamps();
        });

        $now = now();
        $roleIds = [];
        foreach ([
            ['Super admin', 'super_admin', 'Full access, including roles and admin accounts.', array_merge($this->managerPermissions, ['admins.manage']), true],
            ['Manager', 'manager', 'Day-to-day HR operations.', $this->managerPermissions, false],
        ] as [$name, $slug, $description, $permissions, $system]) {
            $roleIds[$slug] = DB::table('roles')->insertGetId([
                'name' => $name, 'slug' => $slug, 'description' => $description,
                'permissions' => json_encode($permissions), 'is_system' => $system,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('password')->constrained('roles')->restrictOnDelete();
        });

        foreach ($roleIds as $slug => $id) {
            DB::table('admins')->where('role', $slug)->update(['role_id' => $id]);
        }

        Schema::table('admins', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }

    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->string('role')->default('manager')->after('password');
        });

        $superId = DB::table('roles')->where('slug', 'super_admin')->value('id');
        DB::table('admins')->update(['role' => 'manager']);
        DB::table('admins')->where('role_id', $superId)->update(['role' => 'super_admin']);

        Schema::table('admins', function (Blueprint $table) {
            $table->dropConstrainedForeignId('role_id');
        });

        Schema::dropIfExists('roles');
    }
};
