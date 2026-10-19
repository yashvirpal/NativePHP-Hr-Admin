# HR Attendance System

See [CONTEXT.md](CONTEXT.md) for the architecture.

- `backend/` – Laravel 13 web dashboard + REST API (Sanctum, SQLite for the prototype)
- `mobile/`  – Laravel 13 + NativePHP Mobile app (calls the backend over HTTPS)

## Running without local PHP (Docker)

All Docker config shared by both projects lives in `docker-infra/`.

```powershell
cd docker-infra
docker compose up --build              # backend :8000, mobile :8001
docker compose --profile pgsql up      # also start PostgreSQL

# any artisan / composer command
docker compose run --rm backend php artisan migrate
docker compose run --rm mobile composer require some/package
```

### Performance note: `vendor/` lives in a Docker volume

Reading thousands of small files through Windows' file share is ~10x slower than the container's own
disk (page loads took 3–5 s). So `backend/vendor` and `mobile/vendor` are **named volumes**, not host folders;
your source code is still bind-mounted and edits apply instantly.

- Install/update packages **inside Docker**: `docker compose run --rm backend composer require vendor/package`
- The `vendor/` folder you see on the host is a stale leftover; it is not used and can be deleted.
  (IDE autocomplete for vendor classes won't see the volume — run `composer install` locally if you need it.)
- Fresh clone / volume deleted: `docker compose run --rm backend composer install` (and `mobile`).

### Testing on a phone with NativePHP Jump

1. Install the **Jump** app on your phone (same Wi-Fi as this PC).
2. `docker compose --profile jump up -d jump`
3. Open <http://localhost:3000/jump/qr> and scan the QR code (or `docker compose logs -f jump`).

The QR code uses this PC's LAN IP (default `172.16.14.106`). If it changes, set it:
`$env:JUMP_IP = "<your-ip>"` before step 2. Windows Firewall must allow inbound TCP
3000, 3001, 3003 (Jump) and 8000 (backend API). Source edits in `mobile/` hot-reload on the phone.

Switch to PostgreSQL by editing the `DB_*` values in `backend/.env`, then
`php artisan config:clear && php artisan migrate`.

## Status (CONTEXT.md section 7)

Backend is built: see [backend/API.md](backend/API.md) for accounts, endpoints and the two web panels.

- Employees: `http://localhost:8000/employee` (own data on the web) and the mobile API `/api/v1`
- Admins (super admin / manager): `http://localhost:8000/admin`
- Dev logins (password `password`): `alice@example.com` (employee), `manager@example.com` / `admin@example.com` (admins)

## Mobile app (`mobile/`)

A NativePHP v4 app with **native screens** (`NativeComponent` + EDGE elements), talking to the backend's employee
API (`/api/v1`). Sign in with an employee account, e.g. `alice@example.com` / `password`.

| Tab | What it does |
|---|---|
| Home | Greeting, **punch in / out**, this month's stats, leave balance, upcoming holidays, pending counts |
| Attendance | Month by month history (prev / next) |
| Leave | Balances, requests, **request leave**, cancel a pending request |
| Overtime | Your **hourly rate** (change it any time), requests with their amount, **request overtime** |
| More | Profile, **holidays**, **my companies** (add / edit / remove, past employers need start + end dates), sign out |

- **Config:** `HR_API_URL` in `mobile/.env` (see `mobile/config/hr.php`). It must be reachable from wherever the PHP
  runs: your PC's LAN IP for Jump / Docker dev (default `http://172.16.14.106:8000/api/v1`), a public **HTTPS** URL
  for a real build (iOS/Android block plain HTTP).
- **Token:** kept in the device keychain / keystore (`SecureStorage`), with an app-cache fallback when the native
  store isn't reachable. A 401 signs the user out; a network error does not.
- **Run it on a phone:** `docker compose --profile jump up -d jump`, then scan the QR code at
  <http://localhost:3000/jump/qr> with the Jump app (see "Testing on a phone" above).
- **Tests:** `docker compose run --rm mobile php artisan test` (API client + every screen via NativePHP's
  `Native::test()` harness, no device needed).
- **UI package:** screens use `nativephp/mobile-ui` (buttons, inputs, select...); it is allow-listed in
  `app/Providers/NativeServiceProvider.php`.
- **Real builds:** `native:install` / `native:run` need the Android SDK or Xcode on the host (not Docker).

