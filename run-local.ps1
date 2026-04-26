# Run VIO (from C:\xampp\htdocs\vio). App URL depends on .env APP_URL.
Set-Location $PSScriptRoot
$ErrorActionPreference = "Stop"

if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
}

if (-not (Test-Path vendor\autoload.php)) {
    composer install --no-interaction
}

if (-not (Test-Path node_modules)) {
    npm install
}

npm run build

php artisan filament:assets
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force

Write-Host ""
Write-Host "If APP_URL is http://localhost/vio/public - open that URL in the browser."
Write-Host "Or run: php artisan serve -> http://127.0.0.1:8000"
Write-Host "Admin: /admin  (admin@example.com / password)"
Write-Host ""
php artisan serve
