# Build and install the app on a phone

Jump (what you use today) only *shows* the app from your PC. A **build** makes a real installed app: PHP runs
inside the phone, so it works without Jump, but it must be able to reach your backend by itself.

| | Android | iPhone |
|---|---|---|
| Build on | **Windows (this PC)** | **A Mac only** (Apple silicon, Xcode 16+) |
| Needed | PHP 8.3+, Composer, 7-Zip, JDK 17, Android Studio + SDK | PHP 8.3+, Composer, Xcode, CocoaPods, Homebrew |
| Account | none | free Apple ID (7-day installs) or Apple Developer Program, $99/yr |

> **iPhone from Windows is not possible.** Apple's toolchain only runs on macOS. Without a Mac, keep using Jump on
> the iPhone (it works fine for testing). Options if you want an installed iPhone app: a Mac you can borrow, a
> rented cloud Mac, or a macOS CI runner (all still need Apple signing).

The app talks to the backend over the internet, so first make the backend reachable over **HTTPS** (step 1).

---

## 1. Give the backend a public HTTPS address (tunnel)

Installed apps can't use your PC's `http://172.16...` address (Android and iOS block plain HTTP, and the phone
may be on mobile data). The project includes a tunnel for that.

```powershell
cd hr-backend-mobile
docker compose up -d                                         # backend must be running
docker compose --profile tunnel up -d api-gateway tunnel     # starts the tunnel
docker compose --profile tunnel logs tunnel                  # find:  https://<random-words>.trycloudflare.com
```

Your app's API address is that URL plus `/api/v1`, e.g. `https://abc-def.trycloudflare.com/api/v1`.

Check it from any browser or phone: open `https://<random>.trycloudflare.com/api/v1/leave-types`. You should see
`{"message":"Unauthenticated."}`. That means it works.

**What is exposed:** only `/api/v1/*`, through a small gateway (`docker-infra/gateway/nginx.conf`). The admin panel,
employee web portal and health check return 404 from outside.

**Before you hand the URL to anyone**, replace the demo accounts. They all use the password `password`:
create real employees in the admin panel with strong passwords and deactivate or delete the demo ones.

Stop it any time: `docker compose --profile tunnel stop tunnel api-gateway`

**Limits to know:**
- The URL **changes every time the tunnel restarts**, and the app has it baked in at build time, so a restart means
  rebuilding the app. For a URL that never changes use a free **ngrok static domain** or a **named Cloudflare
  tunnel** (needs a Cloudflare account and a domain). Ask me and I'll wire either into the compose file.
- The backend still runs on your PC: the app only works while the PC and Docker are on. For a real rollout host the
  backend on a server.

---

## 2. Android, on this Windows PC

### 2a. Install the tools (one time)

Open PowerShell and use winget (if an id isn't found, run `winget search <name>` and pick the match):

```powershell
winget install Microsoft.OpenJDK.17
winget install 7zip.7zip
winget install Google.AndroidStudio
winget install PHP.PHP.8.4          # or: Laravel Herd for Windows (herd.laravel.com), which sets PHP + Composer up for you
winget install Composer.Composer
```

Close and reopen PowerShell, then check `php -v` (8.3 or newer) and `composer -V`.

**PHP needs these extensions enabled** in `php.ini` (copy `php.ini-development` to `php.ini` and remove the `;` before
each): `curl`, `fileinfo`, `gd`, `mbstring`, `openssl`, `pdo_sqlite`, `sqlite3`, `zip`. Also raise
`memory_limit = 2G` (the app-icon step is memory hungry). Herd handles this for you.

**Android Studio** (open it once and let it finish its first-run download):
1. *More Actions, SDK Manager, SDK Platforms:* tick one platform with **API 29 or higher** (e.g. Android 15 / 16).
2. *SDK Tools:* tick **Android SDK Build-Tools** and **Android SDK Platform-Tools**, then Apply.

**Environment variables** (Start menu, "Edit the system environment variables", Environment Variables):

```
JAVA_HOME    = C:\Program Files\Microsoft\jdk-17.x.x.x-hotspot      (check the real folder name)
ANDROID_HOME = C:\Users\<you>\AppData\Local\Android\Sdk
Path        += %JAVA_HOME%\bin
Path        += %ANDROID_HOME%\platform-tools
```

Reopen PowerShell and verify:

```powershell
java -version
adb devices
```

**Optional but recommended:** add `C:\temp` and this project folder to Windows Defender exclusions; builds are much
faster.

### 2b. Prepare the phone
Settings, About phone, tap **Build number** 7 times, then Settings, System, **Developer options**, turn on
**USB debugging**. Plug in the USB cable and accept the "Allow USB debugging" prompt. `adb devices` should list it.

### 2c. Configure the project

Edit `mobile\.env` (create it from `.env.example` if missing):

```dotenv
NATIVEPHP_APP_ID=com.yourcompany.hrattendance   # unique and yours; cannot be changed casually later
NATIVEPHP_APP_VERSION=1.0.0
NATIVEPHP_APP_VERSION_CODE=1

HR_API_URL=https://<your-tunnel>.trycloudflare.com/api/v1

# an installed app should not show debug pages:
APP_ENV=production
APP_DEBUG=false
```

### 2d. Build and install

```powershell
cd mobile
composer install --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
php artisan native:install android      # downloads the Android PHP runtime and builds the native project
php artisan native:run android          # builds, installs and launches on the connected phone
```

Open the app and sign in as one of your employees. If the API address in `.env` changes, run
`php artisan native:run android` again; the address is built into the app.

### 2e. An APK file instead (no cable)

```powershell
php artisan native:credentials          # generate the Android signing key once, and KEEP it safe
php artisan native:package android      # signed package for distribution
```

Copy the resulting `.apk` to the phone and open it (allow "Install unknown apps" for your file manager). For the
Play Store you build an app bundle instead; `native:package` asks which.

**If the build fails**, `php artisan native:debug` prints the environment NativePHP sees (Java, SDK, versions).
Send me that output and the first error.

---

## 3. iPhone, on a Mac

1. Install **Xcode 16+** (App Store), open it once and accept the license, then:
   `xcode-select --install`, Homebrew, and `brew install cocoapods`.
2. Install PHP and Composer, e.g. `/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.4)"`.
3. Copy the `mobile/` folder to the Mac (without `vendor/`, `nativephp/` or your `.env`), recreate `.env` with the
   same values as above, and add your Apple Team ID: `NATIVEPHP_DEVELOPMENT_TEAM=XXXXXXXXXX`
   (Xcode, Settings, Accounts, shows it for your Apple ID).
4. Plug the iPhone in, trust the computer, and enable **Developer Mode** (Settings, Privacy & Security).
5. Then:

```bash
composer install
php artisan native:install ios
php artisan native:run ios        # choose your iPhone
```

With a **free Apple ID** the app expires after 7 days and must be re-installed. With the **$99/yr Developer
Program** you get TestFlight and one-year builds.

---

## Quick reference

| I want to... | Command |
|---|---|
| Start backend | `docker compose up -d` |
| Start the public tunnel | `docker compose --profile tunnel up -d api-gateway tunnel` |
| See the tunnel URL | `docker compose --profile tunnel logs tunnel` |
| Stop the tunnel | `docker compose --profile tunnel stop tunnel api-gateway` |
| Test in Jump (no build) | `docker compose --profile jump up -d jump`, then scan the QR at http://localhost:3000/jump/qr |
| Run the app tests | `docker compose run --rm mobile php artisan test` |
