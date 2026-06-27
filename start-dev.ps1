# ============================================
# start-dev.ps1 — Script untuk menjalankan
# semua server RealtimeChat sekaligus, siap
# diakses dari device lain di jaringan LAN.
# ============================================

# Set PATH untuk PHP, Composer, dan Node.js
$env:Path += ";C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64"
$env:Path += ";C:\laragon\bin\composer"
$env:Path += ";C:\laragon\bin\nodejs\node-v22"

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  RealtimeChat - Starting All Servers..." -ForegroundColor Cyan
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# --------------------------------------------
# 1. Deteksi otomatis IP LAN laptop ini
#    (ambil IPv4 dari adapter yang aktif & bukan loopback/virtual)
# --------------------------------------------
$lanIp = (Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object {
        $_.IPAddress -notlike "127.*" -and
        $_.IPAddress -notlike "169.254.*" -and
        $_.PrefixOrigin -ne "WellKnown" -and
        $_.InterfaceAlias -notmatch "Loopback|vEthernet|WSL|Virtual"
    } | Select-Object -First 1 -ExpandProperty IPAddress)

if (-not $lanIp) {
    Write-Host "[!] Tidak bisa deteksi IP LAN otomatis. Cek manual pakai 'ipconfig'." -ForegroundColor Red
    Write-Host "[!] Lanjut pakai 'localhost' (HANYA bisa diakses dari laptop ini sendiri)." -ForegroundColor Red
    $lanIp = "localhost"
} else {
    Write-Host "[OK] IP LAN terdeteksi: $lanIp" -ForegroundColor Green
}

# --------------------------------------------
# 2. Patch file .env otomatis (APP_URL, REVERB_HOST)
# --------------------------------------------
$envPath = Join-Path (Get-Location) ".env"
if (Test-Path $envPath) {
    $envContent = Get-Content $envPath -Raw
    $envContent = $envContent -replace 'APP_URL=.*', "APP_URL=http://$($lanIp):8000"
    $envContent = $envContent -replace 'REVERB_HOST=.*', "REVERB_HOST=`"$lanIp`""
    Set-Content -Path $envPath -Value $envContent -NoNewline
    Write-Host "[OK] .env sudah di-update otomatis ke IP $lanIp" -ForegroundColor Green
} else {
    Write-Host "[!] File .env tidak ditemukan, lewati patch otomatis." -ForegroundColor Yellow
}

# --------------------------------------------
# 3. Build asset frontend (CSS/JS) sekali sebelum jalan
#    (lebih aman daripada 'npm run dev' karena tidak butuh
#     server Vite terpisah yang harus bisa diakses device lain)
# --------------------------------------------
Write-Host ""
Write-Host "[*] Building frontend assets (npm run build)..." -ForegroundColor Magenta
npm.cmd run build

# Pastikan symlink storage ada (dibutuhkan untuk fitur upload gambar)
php artisan storage:link 2>$null | Out-Null

# --------------------------------------------
# 4. Jalankan Laravel Server (WAJIB --host=0.0.0.0 agar device lain bisa akses)
# --------------------------------------------
Write-Host "[1/2] Starting Laravel Server (http://$($lanIp):8000)..." -ForegroundColor Green
Start-Process powershell -ArgumentList "-NoExit", "-Command", "`$env:Path += ';C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer'; Write-Host 'LARAVEL SERVER' -ForegroundColor Green; php artisan serve --host=0.0.0.0 --port=8000" -WorkingDirectory (Get-Location)

Start-Sleep -Seconds 1

# --------------------------------------------
# 5. Jalankan Reverb WebSocket Server (sudah default listen 0.0.0.0)
# --------------------------------------------
Write-Host "[2/2] Starting Reverb WebSocket Server (port 8080)..." -ForegroundColor Yellow
Start-Process powershell -ArgumentList "-NoExit", "-Command", "`$env:Path += ';C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64;C:\laragon\bin\composer'; Write-Host 'REVERB WEBSOCKET SERVER' -ForegroundColor Yellow; php artisan reverb:start" -WorkingDirectory (Get-Location)

Start-Sleep -Seconds 2

Write-Host ""
Write-Host "============================================" -ForegroundColor Cyan
Write-Host "  Semua server berhasil dijalankan!" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Buka di laptop ini   : http://localhost:8000" -ForegroundColor White
Write-Host "  Buka di HP/laptop lain (WiFi sama) : http://$($lanIp):8000" -ForegroundColor White
Write-Host "============================================" -ForegroundColor Cyan
Write-Host ""

# Otomatis buka browser
Start-Process "http://localhost:8000"

Write-Host "Browser otomatis terbuka! Tekan Enter untuk keluar..." -ForegroundColor Gray
Read-Host
