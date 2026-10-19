<?php

namespace App\Services\Api;

use Illuminate\Support\Facades\Cache;
use Native\Mobile\Facades\SecureStorage;

/**
 * Keeps the API token in the device keychain / keystore.
 *
 * The native secure store is only reachable from a real device or Jump. Anywhere else (tests, plain
 * `php artisan serve`) it reports failure, so we fall back to the app cache rather than losing the session.
 */
class TokenStore
{
    private const KEY = 'hr_api_token';

    public function get(): ?string
    {
        $token = SecureStorage::get(self::KEY);

        if (is_string($token) && $token !== '') {
            return $token;
        }

        $fallback = Cache::get(self::KEY);

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    public function put(string $token): void
    {
        if (SecureStorage::set(self::KEY, $token)) {
            Cache::forget(self::KEY);

            return;
        }

        Cache::forever(self::KEY, $token);
    }

    public function forget(): void
    {
        SecureStorage::delete(self::KEY);
        Cache::forget(self::KEY);
    }

    public function has(): bool
    {
        return $this->get() !== null;
    }
}
