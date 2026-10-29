<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Native\Mobile\Facades\SecureStorage;

/**
 * Keeps the employee's chosen display language on the device.
 *
 * Mirrors TokenStore's native-store-with-cache-fallback pattern (see App\Services\Api\TokenStore) -
 * the native store is only reachable from a real device or Jump.
 *
 * Rendering Hindi ('hi') crashed the native renderer on iOS (confirmed via Jump - the app exits
 * with no error the instant the screen re-renders with Devanagari text). 'hi' is temporarily
 * re-enabled here so it can be tested on Android via Jump too, to find out whether the crash is
 * iOS-specific (likely a font-fallback gap in SwiftUI) or affects both platforms. get() clamps to
 * SUPPORTED either way, so a value saved on one platform can't carry a bad locale onto the other.
 */
class LocalePreference
{
    public const DEFAULT = 'en';

    /** @var list<string> */
    public const SUPPORTED = ['en', 'hi'];

    private const KEY = 'hr_app_locale';

    public function get(): string
    {
        $locale = SecureStorage::get(self::KEY);

        if (is_string($locale) && in_array($locale, self::SUPPORTED, true)) {
            return $locale;
        }

        $fallback = Cache::get(self::KEY);

        return is_string($fallback) && in_array($fallback, self::SUPPORTED, true) ? $fallback : self::DEFAULT;
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
