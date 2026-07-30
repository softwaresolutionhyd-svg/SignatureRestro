Add-Type -AssemblyName System.IO.Compression.FileSystem

$src = 'C:\laragon\www\signature\menu_extract.docx'
$dst = 'C:\laragon\www\signature\menu_docx_unzip'

if (Test-Path $dst) {
    Remove-Item $dst -Recurse -Force
}

[System.IO.Compression.ZipFile]::ExtractToDirectory($src, $dst)
Write-Output $dst
