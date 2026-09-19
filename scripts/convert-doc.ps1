<#
    Konversi .doc (OLE2 biner) menjadi .docx memakai Microsoft Word yang sudah terpasang.

    Proyek ini tidak memaketkan pembaca .doc, sehingga S3 tidak pernah terimpor. Word
    dipakai sekali saja untuk menghasilkan .docx yang dapat dibaca SourceImportService.
    Berkas .doc asli tidak pernah disentuh.

    Pemakaian:
      powershell -ExecutionPolicy Bypass -File scripts/convert-doc.ps1 -Source "<path .doc>" -Destination "<path .docx>"
#>
param(
    [Parameter(Mandatory = $true)][string]$Source,
    [Parameter(Mandatory = $true)][string]$Destination
)

$ErrorActionPreference = 'Stop'

$Source = [System.IO.Path]::GetFullPath($Source)
$Destination = [System.IO.Path]::GetFullPath($Destination)

if (-not (Test-Path -LiteralPath $Source)) {
    Write-Error "Berkas sumber tidak ditemukan: $Source"
    exit 2
}

$word = $null
$doc = $null
try {
    $word = New-Object -ComObject Word.Application
    $word.Visible = $false
    $word.DisplayAlerts = 0

    # ReadOnly + AddToRecentFiles=$false supaya berkas asli dan daftar dokumen terakhir tidak berubah.
    $doc = $word.Documents.Open($Source, [ref]$false, [ref]$true, [ref]$false)

    # wdFormatXMLDocument = 12 (.docx tanpa makro)
    $doc.SaveAs2([ref]$Destination, [ref]12)
    $doc.Close([ref]0)
    $doc = $null

    Write-Output "OK $Destination"
}
catch {
    Write-Error ("Konversi gagal: " + $_.Exception.Message)
    exit 3
}
finally {
    if ($doc -ne $null) { try { $doc.Close([ref]0) } catch {} }
    if ($word -ne $null) {
        try { $word.Quit() } catch {}
        try { [void][System.Runtime.InteropServices.Marshal]::ReleaseComObject($word) } catch {}
    }
}
