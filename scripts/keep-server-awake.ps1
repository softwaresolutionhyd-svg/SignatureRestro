# While Laragon (Apache/MySQL) is running, tell Windows to stay awake.
# When Laragon stops, allow normal sleep again.

$ErrorActionPreference = 'Continue'

Add-Type -Namespace Signature -Name SleepUtil -MemberDefinition @'
[DllImport("kernel32.dll", CharSet = CharSet.Auto, SetLastError = true)]
public static extern uint SetThreadExecutionState(uint esFlags);
'@ -ErrorAction SilentlyContinue

$ES_CONTINUOUS = [uint32]0x80000000
$ES_SYSTEM_REQUIRED = [uint32]0x00000001
$ES_AWAYMODE_REQUIRED = [uint32]0x00000040

function Test-LaragonServerRunning {
    $names = @('httpd', 'nginx', 'mysqld', 'mysql', 'laragon')
    foreach ($n in $names) {
        if (Get-Process -Name $n -ErrorAction SilentlyContinue) {
            return $true
        }
    }
    return $false
}

function Set-KeepAwake([bool]$enabled) {
    if (-not ('Signature.SleepUtil' -as [type])) {
        return
    }
    if ($enabled) {
        # Continuous + system required (+ away mode so media policies don't sleep)
        [void][Signature.SleepUtil]::SetThreadExecutionState(
            $ES_CONTINUOUS -bor $ES_SYSTEM_REQUIRED -bor $ES_AWAYMODE_REQUIRED
        )
    } else {
        [void][Signature.SleepUtil]::SetThreadExecutionState($ES_CONTINUOUS)
    }
}

$wasKeeping = $false
while ($true) {
    $running = Test-LaragonServerRunning
    if ($running) {
        Set-KeepAwake $true
        $wasKeeping = $true
    } elseif ($wasKeeping) {
        Set-KeepAwake $false
        $wasKeeping = $false
    }
    Start-Sleep -Seconds 60
}
