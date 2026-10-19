<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Admin;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Plain <ul class="pagination"> markup, styled by the layout (no Tailwind dependency).
        Paginator::useBootstrapFour();

        // One gate per permission: ->middleware('can:employees.manage'), @can('leave.manage'), $admin->can(...).
        // Only Admin accounts can ever pass; employees (App\Models\User) are always denied.
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn ($user) => $user instanceof Admin && $user->hasPermission($permission));
        }

        // Managing roles is super-admin only, so a role editor can never grant themselves more power.
        Gate::define('roles.manage', fn ($user) => $user instanceof Admin && $user->isSuperAdmin());

        // Pending counts for the admin sidebar badges; only queried when a signed-in admin page renders.
        View::composer('layouts.panel', function ($view) {
            $admin = auth('admin')->user();

            if (! $admin || ! request()->is('admin', 'admin/*')) {
                return;
            }

            $view->with([
                'pendingLeaveCount' => $admin->can('leave.manage') ? LeaveRequest::where('status', 'pending')->count() : 0,
                'pendingOvertimeCount' => $admin->can('overtime.manage') ? OvertimeRequest::where('status', 'pending')->count() : 0,
            ]);
        });
    }
}
