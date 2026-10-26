<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\Admin;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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

        // This app uses hand-written route names, not Laravel's default 'password.reset'.
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            $route = $notifiable instanceof Admin ? 'admin.password.reset' : 'employee.password.reset';

            return route($route, ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);
        });
    }
}
