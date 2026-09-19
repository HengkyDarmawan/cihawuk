<#
.SYNOPSIS
  Uji pemulihan backup ke database target baru, lalu bandingkan jumlah baris.

.DESCRIPTION
  Memulihkan database.sql ke database target (dibuat bila belum ada) memakai akun
  dengan hak CREATE, lalu membandingkan jumlah baris tabel inti dengan database sumber.
  Skrip TIDAK menghapus database target; hapus manual setelah verifikasi.

.EXAMPLE
  powershell -ExecutionPolicy Bypass -File scripts\restore-check.ps1 -BackupDir D:\backup-cihawuk\cihawuk-20260916-150000 -TargetDatabase cihawuk_restore_check -AdminUser root
#>
param(
    [Parameter(Mandatory = $true)][string]$BackupDir,
    [Parameter(Mandatory = $true)][string]$TargetDatabase,
    [string]$AdminUser = 'root',
    [string]$MysqlBin = 'C:\xampp\mysql\bin',
    [string]$SourceDatabase = 'cihawuk_digital'
)

$ErrorActionPreference = 'Stop'
if ($TargetDatabase -notmatch '^[a-z0-9_]+$') { throw 'Nama database target tidak valid.' }
if ($TargetDatabase -eq $SourceDatabase) { throw 'Database target tidak boleh sama dengan sumber.' }

$mysql = Join-Path $MysqlBin 'mysql.exe'
$sql = Join-Path $BackupDir 'database.sql'
$manifest = Get-Content -LiteralPath (Join-Path $BackupDir 'manifest.txt')
$expectedHash = ($manifest | Where-Object { $_ -like 'database_sha256=*' }) -replace 'database_sha256=', ''
$actualHash = (Get-FileHash -LiteralPath $sql -Algorithm SHA256).Hash
if ($expectedHash -ne $actualHash) { throw 'Checksum database.sql tidak cocok dengan manifest.' }

& $mysql -u $AdminUser -e "CREATE DATABASE IF NOT EXISTS $TargetDatabase CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
Get-Content -LiteralPath $sql -Raw | & $mysql -u $AdminUser --default-character-set=utf8mb4 $TargetDatabase
if ($LASTEXITCODE -ne 0) { throw 'Pemulihan database gagal.' }

$tables = @('users', 'roles', 'permissions', 'tickets', 'ticket_messages', 'ticket_status_history', 'ticket_attachments', 'private_files', 'posts', 'source_observations', 'statistic_values', 'audit_logs')
$failed = 0
foreach ($t in $tables) {
    $src = & $mysql -u $AdminUser -N -e "SELECT COUNT(*) FROM $SourceDatabase.$t"
    $dst = & $mysql -u $AdminUser -N -e "SELECT COUNT(*) FROM $TargetDatabase.$t"
    $status = if ($src -eq $dst) { 'OK' } else { $failed++; 'BEDA' }
    Write-Output ("{0,-24} sumber={1,-8} pulih={2,-8} {3}" -f $t, $src, $dst, $status)
}

# Validasi berkas: setiap private_files di DB pulihan harus ada di folder backup.
$keys = & $mysql -u $AdminUser -N -e "SELECT storage_key FROM $TargetDatabase.private_files"
$missing = 0
foreach ($k in $keys) {
    if (-not (Test-Path -LiteralPath (Join-Path (Join-Path $BackupDir 'storage-private') $k))) { $missing++ }
}
Write-Output "Berkas privat tercatat: $(@($keys).Count), tidak ditemukan di backup: $missing"
if ($failed -gt 0 -or $missing -gt 0) { Write-Output 'HASIL: GAGAL'; exit 1 }
Write-Output 'HASIL: PULIH (jalankan smoke test aplikasi terhadap database target sebelum dipakai)'
