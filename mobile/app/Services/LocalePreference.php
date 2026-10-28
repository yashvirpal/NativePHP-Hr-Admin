<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Native\Mobile\Facades\SecureStorage;

/**
 * Keeps the employee's chosen display language ('en' | 'hi') on the device.
 *
 * Mirrors TokenStore's native-store-with-cache-fallback pattern (see App\Services\Api\TokenStore) -
 * the native store is only reachable from a real device or Jump.
 */
class LocalePreference
{
    public const DEFAULT = 'en';

    private const KEY = 'hr_app_locale';

    public function get(): string
    {
        $locale = SecureStorage::get(self::KEY);

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        $fallback = Cache::get(self::KEY);

        return is_string($fallback) && $fallback !== '' ? $fallback : self::DEFAULT;
    }

    public function put(string $locale): void
    {
        if (SecureStorage::set(self::KEY, $locale)) {
            Cache::forget(self::KEY);

            return;
        }

        Cache::forever(self::KEY, $locale);
    }
}
