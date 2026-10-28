<?php

namespace App\NativeComponents;

use App\Services\Api\ApiException;
use App\Services\Api\HrApi;
use App\Services\Api\TokenStore;
use App\Services\LocalePreference;
use Closure;
use Illuminate\Support\Facades\App;
use Native\Mobile\Edge\NativeComponent;

/**
 * Base class for every HR screen: API access, error state and the "session expired" redirect in one place.
 *
 * Subclasses show `$error` (a general message) and `$fieldErrors['field']` (per-input messages) in their views.
 */
abstract class Screen extends NativeComponent
{
    /** A general, user-facing error for the last API call ('' when fine). */
    public string $error = '';

    /** @var array<string, string> first validation message per field */
    public array $fieldErrors = [];

    /** True while an API call triggered by a button is running. */
    public bool $busy = false;

    /**
     * Applies the employee's stored language choice as soon as the screen is instantiated,
     * so every __() call made while building this screen's title/view uses it.
     */
    public function __construct()
    {
        App::setLocale(app(LocalePreference::class)->get());
    }

    protected function api(): HrApi
    {
        return app(HrApi::class);
    }

    protected function signedIn(): bool
    {
        return app(TokenStore::class)->has();
    }

    /**
     * Run an API call and keep screen state consistent.
     *
     * Returns the call's result, or null if it failed. On failure `$error` / `$fieldErrors` are filled,
     * and an expired or missing session sends the user to the login screen.
     */
    protected function call(Closure $request): mixed
    {
        $this->error = '';
        $this->fieldErrors = [];

        try {
            return $request($this->api());
        } catch (ApiException $e) {
            if ($e->isUnauthenticated()) {
                $this->replace('/login');

                return null;
            }

            $this->error = $e->getMessage();
            $this->fieldErrors = $e->fieldMessages();

            return null;
        }
    }

    protected function fieldError(string $field): string
    {
        return $this->fieldErrors[$field] ?? '';
    }

    /** Which bottom tab (by URL) this screen belongs to; null for screens pushed on top of a tab. */
    public function activeTab(): ?string
    {
        return null;
    }
}
