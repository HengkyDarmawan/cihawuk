<#
.SYNOPSIS
  Backup Layanan Digital Desa Cihawuk: database, berkas privat, media publik, dan konfigurasi.

.DESCRIPTION
  Menghasilkan folder bertanggal berisi:
    - database.sql      (mysqldump, --single-transaction)
    - storage-private\  (lampiran, ekspor, dokumen privat)
    - public-media\     (media publik yang disetujui)
    - env.backup        (salinan .env — SIMPAN TERPISAH, akses terbatas)
    - manifest.txt      (jumlah berkas + SHA-256 database.sql)

  Kredensial database dibaca dari .env (DB_MIGRATE_* untuk dump). Password tidak dicetak.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts\backup.ps1 -Destination D:\backup-cihawuk
#>
param(
    [Parameter(Mandatory = $true)][string]$Destination,
    [string]$ProjectRoot = '',
    [string]$MysqlBin = 'C:\xampp\mysql\bin'
)

$ErrorActionPreference = 'Stop'
if ($ProjectRoot -eq '') { $ProjectRoot = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path) }

function Read-DotEnv([string]$path) {
    $values = @{}
    foreach ($line in Get-Content -LiteralPath $path) {
        if ($line -match '^\s*([A-Z0-9_]+)\s*=\s*(.*)$') {
            $values[$Matches[1]] = $Matches[2].Trim().Trim('"')
        }
    }
    return $values
}

$envFile = Join-Path $ProjectRoot '.env'
if (-not (Test-Path -LiteralPath $envFile)) { throw ".env tidak ditemukan di $ProjectRoot" }
$cfg = Read-DotEnv $envFile

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$target = Join-Path $Destination "cihawuk-$stamp"
New-Item -ItemType Directory -Force -Path $target | Out-Null

# 1. Database — password dikirim lewat file opsi sementara agar tidak tampil di daftar proses.
$optFile = Join-Path $env:TEMP "chw-dump-$stamp.cnf"
Set-Content -LiteralPath $optFile -Encoding ascii -Value @(
    '[client]',
    "user=$($cfg['DB_MIGRATE_USERNAME'])",
    "password=$($cfg['DB_MIGRATE_PASSWORD'])",
    "host=$($cfg['DB_HOST'])",
    "port=$($cfg['DB_PORT'])"
)
try {
    $sqlPath = Join-Path $target 'database.sql'
    & (Join-Path $MysqlBin 'mysqldump.exe') "--defaults-extra-file=$optFile" --single-transaction --routines --triggers `
        --default-character-set=utf8mb4 --result-file=$sqlPath $cfg['DB_DATABASE']
    if ($LASTEXITCODE -ne 0) { throw "mysqldump gagal (kode $LASTEXITCODE)" }
}
finally {
    Remove-Item -LiteralPath $optFile -Force -ErrorAction SilentlyContinue
}

# 2. Berkas privat dan media publik.
$private = if ($cfg['STORAGE_PRIVATE_PATH']) { $cfg['STORAGE_PRIVATE_PATH'] } else { Join-Path $ProjectRoot 'storage\private' }
Copy-Item -LiteralPath $private -Destination (Join-Path $target 'storage-private') -Recurse -Force
Copy-Item -LiteralPath (Join-Path $ProjectRoot 'public\media') -Destination (Join-Path $target 'public-media') -Recurse -Force

# 3. Konfigurasi (berisi rahasia) — simpan di lokasi terpisah dengan akses terbatas.
Copy-Item -LiteralPath $envFile -Destination (Join-Path $target 'env.backup') -Force

# 4. Manifest untuk validasi pemulihan.
$privateCount = (Get-ChildItem -LiteralPath (Join-Path $target 'storage-private') -Recurse -File | Where-Object { $_.Name -ne '.gitkeep' }).Count
$mediaCount = (Get-ChildItem -LiteralPath (Join-Path $target 'public-media') -Recurse -File | Where-Object { $_.Name -notin @('.gitkeep', '.htaccess') }).Count
$hash = (Get-FileHash -LiteralPath $sqlPath -Algorithm SHA256).Hash
Set-Content -LiteralPath (Join-Path $target 'manifest.txt') -Encoding utf8 -Value @(
    "created_at=$stamp",
    "database=$($cfg['DB_DATABASE'])",
    "database_sha256=$hash",
    "private_files=$privateCount",
    "public_media_files=$mediaCount"
)
Write-Output "Backup selesai: $target"
Write-Output "Berkas privat: $privateCount, media publik: $mediaCount"
Write-Output 'PENTING: env.backup berisi key enkripsi. Pindahkan ke penyimpanan terpisah dengan akses terbatas.'
