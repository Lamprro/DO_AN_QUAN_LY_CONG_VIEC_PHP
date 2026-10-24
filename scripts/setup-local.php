<?php

use Illuminate\Contracts\Console\Kernel;

// New installation helper. Never rotates an existing key or resets an existing database.
$root = dirname(__DIR__);
chdir($root);
if (! is_file($root.'/vendor/autoload.php')) {
    fwrite(STDERR, "Run composer install first.\n");
    exit(1);
}
if (! is_file($root.'/.env')) {
    $example = file_get_contents($root.'/.env.example');
    $example .= "\nDB_DATABASE=\"".str_replace('\\', '/', $root.'/database/work_management.sqlite')."\"\n";
    file_put_contents($root.'/.env', $example);
}
function artisan(array $args): void
{
    $proc = proc_open(array_merge([PHP_BINARY, 'artisan'], $args), [STDIN, STDOUT, STDERR], $pipes);
    if (! is_resource($proc) || proc_close($proc) !== 0) {
        exit(1);
    }
}
// Check the effective cached environment before changing cache, keys or schema.
require $root.'/vendor/autoload.php';
$guardApp = require $root.'/bootstrap/app.php';
$guardApp->make(Kernel::class)->bootstrap();
if (! $guardApp->environment(['local', 'testing'])) {
    fwrite(STDERR, "Local setup is disabled outside local/testing. Use the production runbook.\n");
    exit(1);
}
artisan(['config:clear', '--no-ansi']);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! config('app.key')) {
    artisan(['key:generate', '--no-ansi']);
}
if (config('database.default') === 'sqlite') {
    $path = config('database.connections.sqlite.database');
    if ($path !== ':memory:' && ! is_file($path)) {
        if (! is_dir(dirname($path))) {
            fwrite(STDERR, "Database parent directory does not exist. Check DB_DATABASE.\n");
            exit(1);
        }
        touch($path);
    }
}
artisan(['migrate', '--force', '--no-ansi']);
if (in_array('--demo', $argv, true)) {
    artisan(['db:seed', '--force', '--no-ansi']);
}
echo "Ready. Run scripts/serve-local.ps1 and scripts/work-ai.ps1 in separate terminals.\n";
