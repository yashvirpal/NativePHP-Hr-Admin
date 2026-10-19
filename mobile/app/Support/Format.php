<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Display formatting for values returned by the backend API.
 * Times are shown exactly as the backend stores them (the same values the web portal shows).
 */
final class Format
{
    public static function date(?string $value, string $format = 'D, d M Y'): string
    {
        return self::parse($value)?->format($format) ?? '-';
    }

    public static function time(?string $value): string
    {
        return self::parse($value)?->format('H:i') ?? '-';
    }

    public static function minutes(int|string|null $minutes): string
    {
        $minutes = (int) $minutes;

        return $minutes > 0 ? intdiv($minutes, 60).'h '.($minutes % 60).'m' : '-';
    }

    /** "150" -> "150.00"; null -> "-" */
    public static function money(int|float|string|null $amount): string
    {
        return $amount === null || $amount === '' ? '-' : number_format((float) $amount, 2, '.', ',');
    }

    /** "2.0" -> "2", "1.5" -> "1.5" */
    public static function days(int|float|string|null $days): string
    {
        $number = (float) $days;

        return $number == (int) $number ? (string) (int) $number : rtrim(rtrim(number_format($number, 1, '.', ''), '0'), '.');
    }

    public static function status(?string $status): string
    {
        return ucfirst(str_replace('_', ' ', (string) $status));
    }

    private static function parse(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone('UTC');
        } catch (Throwable) {
            return null;
        }
    }
}
