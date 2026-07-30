$mediaDir = 'C:\laragon\www\signature\menu_docx_unzip\word\media'
$outFile = 'C:\laragon\www\signature\ocr_output.txt'

$images = Get-ChildItem $mediaDir | Sort-Object {
    if ($_.BaseName -match '^image(\d+)$') { [int]$matches[1] } else { 9999 }
}

if (Test-Path $outFile) {
    Remove-Item $outFile -Force
}

foreach ($image in $images) {
    Add-Content -Path $outFile -Value ("--- " + $image.Name + " ---")
    $text = powershell -NoProfile -ExecutionPolicy Bypass -File 'C:\laragon\www\signature\ocr_image.ps1' -ImagePath $image.FullName
    Add-Content -Path $outFile -Value $text
    Add-Content -Path $outFile -Value ""
}

Write-Output $outFile
