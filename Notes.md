docker compose up -d                       # backend :8000, mobile :8001
docker compose run --rm backend php artisan migrate
docker compose down


docker compose run --rm mobile php artisan list native 2>&1 | Select-String 'native:'; Get-ChildItem mobile\vendor\nativephp\mobile -Recurse -Include *.md -ErrorAction SilentlyContinue | Select-String -Pattern 'jump' -List | Select-Object -First 5 Path

Admin dashboard: http://localhost:8000/admin (admin and HR only), with these pages:
dashboard
employees (create/edit)
departments
attendance by date
leave and overtime approvals
holidays
Seed accounts: all use the password password:
admin@example.com
hr@example.com
alice@example.com
bob@example.com

For the mobile app
Point it at http://172.16.14.106:8000/api/v1 from your phone (the backend container already publishes :8000).
Log in with device_name, then send the returned token as a Bearer header.
Next, I can add a BACKEND_API_URL setting and an API client to the mobile/ project. I left that for you, since you said you'd take the mobile app.



docker compose run --rm backend php artisan migrate:fresh --seed


# Restart Jump after changing mobile code
docker compose --profile jump restart jump

# Stop Jump
docker compose --profile jump stop jump

# PC's IP changed? Pass the new one (also update HR_API_URL in mobile/.env)
$env:JUMP_IP = "192.168.1.50"
docker compose --profile jump up -d --force-recreate jump

# Run Jump in the foreground with the QR printed live (Ctrl+C stops it)
docker compose --profile jump stop jump
docker compose run --rm --service-ports jump
