$HostsFile = 'C:\Windows\System32\drivers\etc\hosts'
$entry = '127.0.0.1      signature.restro       #laragon magic!'
$content = Get-Content $HostsFile
if ($content -notmatch 'signature\.restro') {
    Add-Content -Path $HostsFile -Value $entry -Encoding ASCII
    Write-Host 'Added signature.restro to hosts.'
} else {
    Write-Host 'signature.restro already in hosts.'
}
