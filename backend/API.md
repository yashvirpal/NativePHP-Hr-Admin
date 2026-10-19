# HR Backend

Two kinds of people, two tables, two guards:

| Who | Table / model | Guard | Signs in at | Can do |
|---|---|---|---|---|
| **Employee** | `users` / `User` (+ `employees` profile) | `web` (session) and Sanctum tokens | `/employee` (web) and the mobile API | See and manage **their own** data: attendance, leave, overtime, holidays, employer companies |
| **Admin** | `admins` / `Admin` (+ a **role**, see below) | `admin` (session) | `/admin` | Whatever their role's permissions allow; every admin gets the dashboard |

Credentials are never shared across tables: admin credentials are rejected at `/employee/login` and on the API,
and employee credentials are rejected at `/admin/login`. Both panels can be signed in at once in one browser.

## Roles and permissions (admins)

Each admin has exactly one **role**; a role is a named set of **permissions**. Pages, actions, sidebar items and
dashboard sections all follow the role. Roles are managed at `/admin/roles` by super admins only.

| Permission | Allows |
|---|---|
| `employees.view` | Employee list |
| `employees.manage` | Create / edit employees and their logins |
| `departments.manage` | Departments |
| `attendance.view` | Attendance by day |
| `leave.manage` | See and decide leave requests |
| `overtime.manage` | See and decide overtime requests |
| `holidays.manage` | Holidays |
| `admins.manage` | Admin accounts (`/admin/admins`) |

- **Super admin** (system role, locked) implicitly holds *every* permission, including ones added later, and is the
  only role that can manage roles.
- **No privilege escalation:** an admin with `admins.manage` can only assign roles whose permissions are a subset of
  their own, can never assign or edit the Super admin role or accounts, and the last super admin can't be demoted.
- A role that is assigned to admins can't be deleted. Changes to a role apply on the next request.
- Gates: `can:employees.manage` in routes, `@can('leave.manage')` in views, `$admin->can('...')` in code.
- The migration turns the old fixed `super_admin` / `manager` values into the "Super admin" and "Manager" roles, so
  existing admins keep their access.

Seeded roles (10): Super admin, Manager, HR Manager, HR Executive, Payroll Officer, Attendance Clerk,
Leave Approver, Overtime Approver, Department Head, Auditor.
## One service, two clients

`App\Services\EmployeePortalService` is the single implementation of everything an employee can do or see.
The mobile API (`Api\V1\*`) and the employee web portal (`Employee\*`) are thin controllers that validate (shared
`App\Http\Requests\*`), call that service, and render JSON or Blade. A rule changed there changes both clients.

## Seeded dev accounts (`php artisan db:seed`, password `password`)

Reset everything with `php artisan migrate:fresh --seed`; re-running `db:seed` is idempotent.

**Admins** (`/admin`): `admin@example.com`, `owner@example.com` (Super admin), `manager@example.com` (Manager), and one
account per remaining role: `priya.manager@` (HR Manager), `rajesh.manager@` (HR Executive), `sunita.manager@` (Payroll Officer),
`deepak.manager@` (Attendance Clerk), `lakshmi.manager@` (Leave Approver), `farhan.manager@` (Overtime Approver),
`gita.manager@example.com` (Auditor). 10 in total.

**Employees** (`/employee` and the mobile app): `alice@example.com`, `bob@`, `rahul@`, `sneha@`, `arjun@`, `neha@`,
`karan@`, `divya@`, `vikram@`, `anita@`, `rohit@`, `meera@`, `sanjay@`, `pooja@`, `amit@`, `kavya@`, `manish@`,
`ishaan@`, `tanvi@`, `nikhil@example.com`. 20 in total.

Volumes: 10 admins, 20 employees, 10 departments, 10 shifts, 10 leave types, 12 holidays, ~30 companies,
180 leave balances, 27 overtime rates (some with a raise), 40 leave requests, ~280 attendance records (last 21 days; today is empty so you can punch in),
16 overtime requests, ~33 approvals.

## Mobile / REST API (employees)

Base URL `http://<host>:8000/api/v1` - JSON in/out - `Authorization: Bearer <token>` (Sanctum).
Validation errors -> `422 {message, errors}`; someone else's record -> `403`; no token -> `401`.
Admins do not use the API: approvals happen in the web admin panel.

| Method | Path | Notes |
|---|---|---|
| POST | `/auth/login` | `email, password, device_name` -> `{token, user}`. Needs an *active employee profile* |
| POST | `/auth/logout` | revokes the current token |
| GET | `/me` | user + employee, department, shift |
| GET | `/dashboard` | today's status, month stats, balances, upcoming holidays, pending counts |
| GET | `/attendance/today` | `{record, is_punched_in}` |
| POST | `/attendance/punch-in` | optional `latitude, longitude, device_info`. Time and `source` are set by the server. Late = after shift start + grace. 422 if already in |
| POST | `/attendance/punch-out` | 422 if not punched in; updates `worked_minutes` |
| GET | `/attendance?month=YYYY-MM` | paginated history |
| GET | `/leave-types`, `/leave-balances` | balances for the current year |
| GET | `/leave-requests` | own, paginated |
| POST | `/leave-requests` | `leave_type_id, start_date, end_date, reason?`. Days = Mon-Fri minus public holidays. Rejects overlap / insufficient balance / cross-year |
| POST | `/leave-requests/{id}/cancel` | own + pending only |
| GET | `/holidays?year=` | |
| GET / POST | `/overtime-requests` | POST: `work_date (<= today), minutes (1-720), reason?`. Each row carries `rate_per_hour` and `amount` (see below) |
| GET | `/overtime-rate` | `{rate, effective_from, history[]}`: current hourly rate (`null` if never set) and every change, newest first |
| PUT | `/overtime-rate` | `hourly_rate` (0 - 99999.99, max 2 decimals). Applies to overtime **submitted from now on** |

**Overtime pay.** `amount = minutes / 60 x rate_per_hour`, rounded to 2 decimals. Both `rate_per_hour` and `amount` are a
**snapshot taken when the request is submitted**; changing the rate afterwards never recalculates existing requests
(raising or lowering it). Requests submitted before any rate was set keep `rate_per_hour = null` / `amount = null`.
Setting the rate to its current value is a no-op (no history row). Rates are per employee.
| GET / POST | `/companies` | the employee's own employer details |
| PUT / DELETE | `/companies/{id}` | own only; `name` required; `website` must be a URL |

Company fields: `name*, industry, designation, website, phone, address, started_on, ended_on, is_current`.

Dates: when `is_current` is **false** (or omitted, as an unticked checkbox is), both `started_on` and `ended_on` are
required, `ended_on` must be on/after `started_on`, and neither may be in the future. When `is_current` is **true**,
dates are optional and `ended_on` is always stored as `null`.

## Web portals

| URL | Panel |
|---|---|
| `/` | redirects to `/employee` |
| `/employee`, `/employee/login` | employee portal: dashboard with punch in/out, attendance, leave (apply/cancel), overtime, holidays, my companies |
| `/admin`, `/admin/login` | admin panel: dashboard, employees (create/edit), departments, attendance, leave & overtime approvals, holidays, admins (super admin only) |

## Known limitations
- Permissions are page/action level (see "Roles and permissions"); there is no per-record scoping such as "only my department".
- A day's attendance is keyed to the server's calendar date, so overnight shifts that cross midnight aren't handled yet.
- Leave is whole days only (no half-days) and can't span two calendar years.
- Overtime is priced with the rate in force when the request is *submitted*, not the rate on the day worked; amounts have no currency symbol.
- Weekends are fixed Sat/Sun; per-shift working days aren't modelled.
- Employee companies are display-only profile data; they do not scope or isolate any other data (single-tenant).
- Timezone follows `APP_TIMEZONE` (defaults to UTC) - set it in `.env` before real use.
