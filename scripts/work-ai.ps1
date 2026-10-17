param([switch]$Drain)
$ErrorActionPreference = 'Stop'
$workerRoot = Split-Path -Parent $PSScriptRoot
$workerArgs = @('artisan', 'queue:work', 'database', '--queue=work-management-ai', '--sleep=1', '--tries=3', '--timeout=45')
if ($Drain) { $workerArgs += '--stop-when-empty' }
Push-Location -LiteralPath $workerRoot
try {
    & php @workerArgs
    if ($LASTEXITCODE -ne 0) { throw "Worker exited with code $LASTEXITCODE" }
} finally { Pop-Location }
