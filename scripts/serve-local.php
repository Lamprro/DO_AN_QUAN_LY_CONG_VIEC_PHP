<?php

$root = dirname(__DIR__);
$port = $argv[1] ?? '8123';
if (! ctype_digit($port) || (int) $port < 1024 || (int) $port > 65535) {
    fwrite(STDERR, "Port must be 1024..65535\n");
    exit(1);
}
$args = [PHP_BINARY, '-d', 'upload_max_filesize=6M', '-d', 'post_max_size=7M'];
if (PHP_OS_FAMILY === 'Windows' && ! extension_loaded('zip')) {
    array_push($args, '-d', 'extension=zip');
}
array_push($args, '-S', '127.0.0.1:'.$port, $root.'/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php');
$process = proc_open($args, [STDIN, STDOUT, STDERR], $pipes, $root.'/public');
exit(is_resource($process) ? proc_close($process) : 1);
