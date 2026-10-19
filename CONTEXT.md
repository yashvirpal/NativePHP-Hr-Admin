# Employee Attendance & HR System

## 1. Project Overview
Build an employee attendance and HR system with:
- **Mobile app:** Employees use Android and iOS.
- **Web dashboard:** Admin/HR manage employees and review records.
- **Backend API:** Shared business logic and the single source of truth.

## 2. Recommended Structure

Keep two Laravel projects side by side:

```text
hr-system/
├── backend/   # Laravel web dashboard + REST API
└── mobile/    # Laravel + NativePHP Mobile app
```

### Backend (`backend/`)
Responsible for:
- Login, users, roles and permissions
- Employee and department management
- Attendance punch-in/punch-out
- Leave requests and approvals
- Holidays and overtime
- Reports and attendance history
- Database and API

Suggested Laravel areas:
- `app/Models/`
- `app/Http/Controllers/Admin/`
- `app/Http/Controllers/Api/V1/`
- `app/Services/`
- `app/Policies/`
- `database/migrations/`
- `resources/views/admin/`
- `routes/web.php` and `routes/api.php`

### Mobile (`mobile/`)
Responsible for employee-facing screens:
- Login and dashboard
- Punch-in/punch-out
- Attendance history
- Leave requests
- Holidays and overtime

Suggested areas:
- `app/Http/Controllers/`
- `app/Services/Api/`
- `resources/views/`
- `routes/web.php`

The mobile app calls the backend over **HTTPS REST APIs**. It must not connect directly to the central database.

## 3. Database

- **Recommended for production:** PostgreSQL (MySQL is also suitable).
- **For an early prototype:** SQLite is fine.
- Keep one central database in the backend. The mobile app may optionally use local SQLite for offline caching.

Laravel migrations create or update the database schema, but **they do not transfer existing records** when switching databases. Moving from SQLite to PostgreSQL requires exporting/importing data and testing queries, dates, constraints and relationships.

Example PostgreSQL `.env` settings:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=hr_attendance
DB_USERNAME=hr_app
DB_PASSWORD=your_secure_password
```

After changing database configuration:

```bash
php artisan config:clear
php artisan migrate
```

## 4. Suggested Main Models

- `User`
- `Employee`
- `Department`
- `Shift`
- `AttendanceRecord`
- `AttendancePunch`
- `LeaveType`
- `LeaveBalance`
- `LeaveRequest`
- `Holiday`
- `OvertimeRequest`
- `Approval`

Use migrations for schema changes and seeders for development/test data.

## 5. Authentication & Security

- Use Laravel Sanctum token authentication for the mobile API.
- Use Laravel session authentication for the admin website.
- Enforce roles and permissions on the backend.
- Always use HTTPS in production.
- Never place database credentials in the mobile app.
- Validate punch-in/out and approval requests on the server.

## 6. Development Tools

- PHP, Composer and Laravel
- NativePHP Mobile
- PostgreSQL or MySQL
- Filament (optional, for a faster admin dashboard)
- Android Studio / Android SDK for Android builds
- Xcode on a compatible Mac for iOS builds

## 7. Recommended Build Order

1. Create the `backend` Laravel project and configure its database.
2. Create migrations and models for employees, shifts and attendance.
3. Build authentication and role/permission rules.
4. Implement attendance, leave, holidays and overtime services.
5. Build and test REST API endpoints.
6. Build the admin dashboard.
7. Create the `mobile` NativePHP project and connect it to the API.
8. Test on real devices, then prepare Android and iOS releases.

**Core principle:** the backend owns the business rules and central data; the web dashboard and mobile app are two clients of that backend.
