param([ValidateRange(1024,65535)][int]$Port = 8123)
$taskProjectRoot = Split-Path -Parent $PSScriptRoot
$taskPublicPath = Join-Path $taskProjectRoot 'public'
$taskRouterPath = Join-Path $taskProjectRoot 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'
if (-not (Test-Path -LiteralPath $taskRouterPath)) { throw 'Run composer install first.' }
Push-Location -LiteralPath $taskPublicPath
try {
    & php -d extension=zip -d upload_max_filesize=6M -d post_max_size=7M -S "127.0.0.1:$Port" $taskRouterPath
    $taskServerExitCode = $LASTEXITCODE
} finally {
    Pop-Location
}
exit $taskServerExitCode
