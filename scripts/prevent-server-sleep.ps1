# Prevent Windows sleep/hibernate while this machine hosts Signature (Laragon).
# Safe for restaurant server PC: plugged-in (AC) sleep = Never.

$ErrorActionPreference = 'Stop'

function Invoke-PowerCfg {
    param([Parameter(Mandatory)][string[]]$PowerArgs)
    & powercfg.exe @PowerArgs | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "powercfg failed: $($PowerArgs -join ' ')"
    }
}

Write-Host 'Applying AC power settings (sleep/hibernate Never)...'

# Plugged in: never sleep / never hibernate
Invoke-PowerCfg -PowerArgs @('/change', 'standby-timeout-ac', '0')
Invoke-PowerCfg -PowerArgs @('/change', 'hibernate-timeout-ac', '0')

# Battery (if laptop used as server): also never — POS must stay up
Invoke-PowerCfg -PowerArgs @('/change', 'standby-timeout-dc', '0')
Invoke-PowerCfg -PowerArgs @('/change', 'hibernate-timeout-dc', '0')

# Hybrid sleep off
Invoke-PowerCfg -PowerArgs @('/SETACVALUEINDEX', 'SCHEME_CURRENT', 'SUB_SLEEP', 'HYBRIDSLEEP', '0')
Invoke-PowerCfg -PowerArgs @('/SETDCVALUEINDEX', 'SCHEME_CURRENT', 'SUB_SLEEP', 'HYBRIDSLEEP', '0')

# Screen can turn off (saves display); PC stays awake
# Leave monitor timeout alone if you want — uncomment to set 15 min:
# Invoke-PowerCfg -PowerArgs @('/change', 'monitor-timeout-ac', '15')

Invoke-PowerCfg -PowerArgs @('/SETACTIVE', 'SCHEME_CURRENT')

Write-Host 'OK: PC will not sleep while power settings stay like this.'
Write-Host 'Screen still turn off ho sakti hai — software chalta rahega.'
