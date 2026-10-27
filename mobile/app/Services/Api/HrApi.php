<?php

namespace App\Services\Api;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Typed wrapper over the backend's employee REST API (backend/API.md, `/api/v1`).
 *
 * Every method returns decoded JSON as an array and throws ApiException on failure:
 *   401 -> the stored token is dropped and ApiException::isUnauthenticated() is true
 *   422 -> ApiException::fieldMessages() carries the per-field validation messages
 *   0   -> the server could not be reached
 */
class HrApi
{
    public function __construct(private TokenStore $tokens) {}

    // ─── Auth ───────────────────────────────────────────────────────────────────

    /** Signs in and stores the token. @return array the signed-in user (with employee profile) */
    public function login(string $email, string $password): array
    {
        $data = $this->send('POST', 'auth/login', [
            'email' => $email,
            'password' => $password,
            'device_name' => config('hr.device_name'),
        ], authenticated: false);

        $this->tokens->put($data['token']);

        return $data['user'];
    }

    /** Asks the backend to email a password reset link. @return array the server's confirmation message */
    public function forgotPassword(string $email): array
    {
        return $this->send('POST', 'auth/forgot-password', ['email' => $email], authenticated: false);
    }

    /** Revokes the token on the server (best effort) and forgets it locally. */
    public function logout(): void
    {
        try {
            $this->send('POST', 'auth/logout');
        } catch (ApiException) {
            // Offline or already expired: either way this device is signing out.
        } finally {
            $this->tokens->forget();
        }
    }

    public function me(): array
    {
        return $this->send('GET', 'me');
    }

    // ─── Dashboard & attendance ─────────────────────────────────────────────────

    public function dashboard(): array
    {
        return $this->send('GET', 'dashboard');
    }

    public function today(): array
    {
        return $this->send('GET', 'attendance/today');
    }

    public function punchIn(array $meta = []): array
    {
        return $this->send('POST', 'attendance/punch-in', $meta);
    }

    public function punchOut(array $meta = []): array
    {
        return $this->send('POST', 'attendance/punch-out', $meta);
    }

    public function attendance(?string $month = null): array
    {
        return $this->send('GET', 'attendance', array_filter(['month' => $month]));
    }

    /**
     * Every day of the month for the calendar view, already classified (present/late/absent/
     * leave/logged/unmarked) and combining punch, manual-entry, overtime and leave data.
     *
     * @return list<array{work_date:string, status:string, leave_type:?string, first_in_at:?string,
     *     last_out_at:?string, worked_minutes:int, regular_minutes:int, overtime_minutes:int, overtime_amount:?string}>
     */
    public function attendanceCalendar(string $month): array
    {
        return $this->send('GET', 'attendance/calendar', ['month' => $month]);
    }

    /** Independent of punches - a user-declared log, not reconciled with worked_minutes. */
    public function logManualRegularHours(string $workDate, int $minutes, ?string $reason = null): array
    {
        return $this->send('POST', 'attendance/manual', array_filter([
            'work_date' => $workDate,
            'minutes' => $minutes,
            'reason' => $reason,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** @return array{month: array{regular_minutes:int, overtime_minutes:int, overtime_amount:?string}, year: array{regular_minutes:int, overtime_minutes:int, overtime_amount:?string}} */
    public function attendanceTotals(?string $month = null): array
    {
        return $this->send('GET', 'attendance/totals', array_filter(['month' => $month]));
    }

    /** The employee's current regular/overtime/leave status for one day - what "log hours" prefills. */
    public function dayEntry(string $workDate): array
    {
        return $this->send('GET', 'attendance/manual', ['work_date' => $workDate]);
    }

    /**
     * Regular hours, overtime hours/amount and leave days taken, either over a trailing window
     * (1, 3 or 6 months, including the current month) or over an explicit [$from, $to] custom
     * range - for the "Earning" screen. A custom range takes precedence when given.
     *
     * @return array{months:?int, from:string, to:string, regular_minutes:int, overtime_minutes:int,
     *     overtime_amount:?string, leave_days:string}
     */
    public function earnings(?int $months = null, ?string $from = null, ?string $to = null): array
    {
        return $this->send('GET', 'earnings', array_filter([
            'months' => $months,
            'from' => $from,
            'to' => $to,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    // ─── Leave ──────────────────────────────────────────────────────────────────

    public function leaveBalances(): array
    {
        return $this->send('GET', 'leave-balances');
    }

    public function leaveRequests(int $page = 1): array
    {
        return $this->send('GET', 'leave-requests', ['page' => $page]);
    }

    public function cancelLeave(int $id): array
    {
        return $this->send('POST', "leave-requests/{$id}/cancel");
    }

    /** No $leaveTypeId means Annual Leave — what the calendar's "mark as leave" flow always means. */
    public function requestLeave(?int $leaveTypeId, string $startDate, string $endDate, ?string $reason = null): array
    {
        return $this->send('POST', 'leave-requests', array_filter([
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'reason' => $reason,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    // ─── Holidays & overtime ────────────────────────────────────────────────────

    public function holidays(?int $year = null): array
    {
        return $this->send('GET', 'holidays', array_filter(['year' => $year]));
    }

    public function overtimeRequests(int $page = 1): array
    {
        return $this->send('GET', 'overtime-requests', ['page' => $page]);
    }

    public function requestOvertime(string $workDate, int $minutes, ?string $reason = null): array
    {
        return $this->send('POST', 'overtime-requests', array_filter([
            'work_date' => $workDate,
            'minutes' => $minutes,
            'reason' => $reason,
        ], fn ($v) => $v !== null && $v !== ''));
    }

    /** @return array{rate: ?string, effective_from: ?string, history: list<array{hourly_rate: string, effective_from: string}>} */
    public function overtimeRate(): array
    {
        return $this->send('GET', 'overtime-rate');
    }

    /** New rate applies to overtime submitted from now on; the backend keeps old requests at their own rate. */
    public function setOvertimeRate(string $hourlyRate): array
    {
        return $this->send('PUT', 'overtime-rate', ['hourly_rate' => $hourlyRate]);
    }

    // ─── Employer companies ─────────────────────────────────────────────────────

    public function companies(): array
    {
        return $this->send('GET', 'companies');
    }

    public function saveCompany(array $data, ?int $id = null): array
    {
        return $id === null
            ? $this->send('POST', 'companies', $data)
            : $this->send('PUT', "companies/{$id}", $data);
    }

    public function deleteCompany(int $id): void
    {
        $this->send('DELETE', "companies/{$id}");
    }

    // ─── Transport ──────────────────────────────────────────────────────────────

    private function send(string $method, string $path, array $data = [], bool $authenticated = true): array
    {
        $request = Http::baseUrl(rtrim((string) config('hr.api_url'), '/'))
            ->acceptJson()
            ->timeout((int) config('hr.timeout'));

        if ($authenticated) {
            $token = $this->tokens->get();

            if ($token === null) {
                throw new ApiException('Please sign in.', 401);
            }

            $request = $request->withToken($token);
        }

        try {
            $response = $method === 'GET'
                ? $request->get($path, $data)
                : $request->send($method, $path, ['json' => $data]);
        } catch (ConnectionException) {
            throw new ApiException('Cannot reach the server. Check your connection and try again.');
        }

        if ($response->successful()) {
            return $response->json() ?? [];
        }

        if ($response->status() === 401 && $authenticated) {
            $this->tokens->forget();

            throw new ApiException('Your session has expired. Please sign in again.', 401);
        }

        $body = $response->json() ?? [];

        throw new ApiException(
            (string) ($body['message'] ?? 'Something went wrong. Please try again.'),
            $response->status(),
            is_array($body['errors'] ?? null) ? $body['errors'] : [],
        );
    }
}
