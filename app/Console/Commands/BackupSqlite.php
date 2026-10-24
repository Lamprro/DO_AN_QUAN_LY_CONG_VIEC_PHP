<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class BackupSqlite extends Command
{
    protected $signature = 'app:backup-sqlite {destination : New absolute directory outside the application} {--workers-stopped : Confirm all worker/scheduler writers are stopped}';

    protected $description = 'Create a maintenance-mode SQLite + private task file snapshot; keep APP_KEY separately';

    public function handle(): int
    {
        if (config('database.default') !== 'sqlite' || config('filesystems.disks.task_files.driver') !== 'local') {
            $this->error('This command supports SQLite with local private task files only. Use the database-specific backup runbook.');

            return self::FAILURE;
        }
        if (! app()->isDownForMaintenance() || ! $this->option('workers-stopped')) {
            $this->error('Enter maintenance mode and stop all workers/schedulers before confirming --workers-stopped.');

            return self::FAILURE;
        }
        $input = (string) $this->argument('destination');
        $parent = realpath(dirname($input));
        $name = basename($input);
        if (! $parent || ! preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $input) || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/', $name)) {
            $this->error('Choose a new absolute directory under an existing private backup directory.');

            return self::FAILURE;
        }
        $destination = $parent.DIRECTORY_SEPARATOR.$name;
        $base = strtolower(str_replace('\\', '/', realpath(base_path())));
        $candidate = strtolower(str_replace('\\', '/', $destination));
        if ($candidate === $base || str_starts_with($candidate, $base.'/') || file_exists($destination) || is_link($destination)) {
            $this->error('Destination must be new and outside the source/application tree.');

            return self::FAILURE;
        }
        $privateRoot = realpath(config('filesystems.disks.task_files.root'));
        if ($privateRoot) {
            $privateRoot = strtolower(str_replace('\\', '/', $privateRoot));
            if ($candidate === $privateRoot || str_starts_with($candidate, $privateRoot.'/')) {
                $this->error('Destination must also be outside private file storage.');

                return self::FAILURE;
            }
        }
        try {
            File::makeDirectory($destination, 0700);
            $database = $destination.'/database.sqlite';
            DB::connection()->getPdo()->exec('VACUUM INTO '.DB::connection()->getPdo()->quote($database));
            chmod($database, 0600);
            $source = config('filesystems.disks.task_files.root');
            $target = $destination.'/task-files';
            File::makeDirectory($target, 0700);
            $manifest = ['format' => 1, 'created_at' => now()->toIso8601String(), 'database_driver' => 'sqlite', 'app_key_included' => false, 'files' => ['database.sqlite' => hash_file('sha256', $database)]];
            if (is_dir($source)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->isLink()) {
                        throw new \RuntimeException('Symlink in private files');
                    }
                    if (! $file->isFile()) {
                        continue;
                    }
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(rtrim($source, '/\\')) + 1));
                    $path = $target.'/'.$relative;
                    File::ensureDirectoryExists(dirname($path), 0700);
                    if (! copy($file->getPathname(), $path)) {
                        throw new \RuntimeException('File copy failed');
                    }
                    chmod($path, 0600);
                    $manifest['files']['task-files/'.$relative] = hash_file('sha256', $path);
                }
            }
            $snapshot = new \PDO('sqlite:'.$database);
            if ($snapshot->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || $snapshot->query('PRAGMA foreign_key_check')->fetchAll()) {
                throw new \RuntimeException('Snapshot integrity check failed');
            }
            foreach ($snapshot->query("SELECT data FROM task_activities WHERE type = 'ATTACHMENT'") as $row) {
                $data = json_decode($row['data'], true, flags: JSON_THROW_ON_ERROR);
                $path = 'task-files/'.($data['path'] ?? '');
                if (! isset($manifest['files'][$path]) || (isset($data['sha256']) && ! hash_equals($data['sha256'], $manifest['files'][$path]))) {
                    throw new \RuntimeException('Attachment missing or changed');
                }
            }
            foreach ($manifest['files'] as $path => $hash) {
                if (! hash_equals($hash, hash_file('sha256', $destination.'/'.$path))) {
                    throw new \RuntimeException('Snapshot hash mismatch');
                }
            }
            File::put($destination.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            chmod($destination.'/manifest.json', 0600);
            $this->info('Snapshot verified. Store APP_KEY separately and rehearse restoration before relying on this backup.');
            $this->warn('Redis invitation state is not included; retain Redis persistence or reissue pending invitations after recovery.');

            return self::SUCCESS;
        } catch (\Throwable) {
            $this->error('Backup incomplete. Keep maintenance mode while investigating locally; do not use a directory without a verified manifest.');

            return self::FAILURE;
        }
    }
}
