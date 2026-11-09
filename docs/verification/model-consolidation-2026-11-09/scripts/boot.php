<?php
// Usage: php boot.php <app_base_path> <script.php>
// Boots the Laravel app at <app_base_path> (DB taken from env DB_CONNECTION/DB_DATABASE) and runs <script.php>.
$base = realpath($argv[1]);
require $base.'/vendor/autoload.php';
// vendor/ may be a junction to another checkout: resolve App\ and Database\ classes from $base first.
spl_autoload_register(function ($class) use ($base) {
    foreach (['App\\' => '/app/', 'Database\\Factories\\' => '/database/factories/', 'Database\\Seeders\\' => '/database/seeders/'] as $prefix => $dir) {
        if (str_starts_with($class, $prefix)) {
            $file = $base.$dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
            if (is_file($file)) {
                require $file;
            }

            return;
        }
    }
}, true, true);
$app = require $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo 'APP: '.base_path().' | DB: '.config('database.default').' '.config('database.connections.'.config('database.default').'.database').PHP_EOL;
require $argv[2];
