<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionPreflight extends Command
{
    protected $signature = 'app:preflight {--production : Enforce production configuration} {--runtime : Require fresh scheduler and worker heartbeats} {--json : Print redacted machine-readable checks}';

    protected $description = 'Check deployment requirements without printing credentials or changing domain data';

    public function handle(): int
    {
        $checks = [];
        $add = function (string $name, bool $ok, string $instruction) use (&$checks) {
            $checks[] = ['check' => $name, 'passed' => $ok, 'action' => $ok ? '' : $instruction];
        };
        $add('php', version_compare(PHP_VERSION, '8.2.0', '>='), 'Use PHP >= 8.2 supported by composer.lock.');
        foreach (['pdo', 'mbstring', 'openssl', 'fileinfo', 'ctype', 'tokenizer', 'xml', 'dom', 'session'] as $extension) {
            $add('extension.'.$extension, extension_loaded($extension), 'Install the required PHP extension.');
        }
        try {
            app('encrypter');
            $validKey = true;
        } catch (\Throwable) {
            $validKey = false;
        }
        $add('app_key', $validKey, 'Set a valid persistent APP_KEY; never regenerate a live key.');
        $add('storage', is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')), 'Grant the service user write access to storage and bootstrap/cache.');
        $add('private_files', config('filesystems.disks.task_files.driver') === 'local' && ! config('filesystems.disks.task_files.serve'), 'Keep task files private; only expose public/ as document root.');
        try {
            DB::select('select 1');
            $required = ['users', 'sessions', 'password_reset_tokens', 'jobs', 'failed_jobs', 'workspaces', 'projects', 'tasks', 'ai_runs', 'task_reminders', 'calendar_proposals', 'task_watchers', 'task_templates', 'task_template_runs'];
            $add('database.tables', collect($required)->every(fn ($table) => Schema::hasTable($table)), 'Run reviewed migrations on the target database.');
            $migrator = app('migrator');
            $pending = array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan());
            $add('database.migrations', count($pending) === 0, 'Back up, then run php artisan migrate --force.');
            if ($this->option('production')) {
                $add('no_demo_accounts', ! DB::table('users')->whereIn('email', array_map(fn ($n) => 'demo'.$n.'@example.test', range(1, 5)))->exists(), 'Use a clean production database; do not publish public demo accounts.');
            }
        } catch (\Throwable) {
            $add('database.connection', false, 'Check database connectivity and migrations locally; secrets are not printed.');
        }
        foreach (array_unique([config('cache.default'), config('workspace.invitation_store')]) as $store) {
            try {
                // Ephemeral infrastructure probe only; never clears application cache.
                $key = 'preflight:'.bin2hex(random_bytes(12));
                $cache = Cache::store($store);
                $lock = $cache->lock($key.':lock', 10);
                $locked = $lock->get();
                try {
                    $cache->put($key, 'ok', 10);
                    $ok = $locked && $cache->get($key) === 'ok';
                } finally {
                    $cache->forget($key);
                    if ($locked) {
                        $lock->release();
                    }
                }
                $add('cache.'.$store, $ok, 'Check persistent shared cache connectivity and atomic lock support.');
            } catch (\Throwable) {
                $add('cache.'.$store, false, 'Check cache connectivity and atomic locks; diagnostic secrets are not printed.');
            }
        }
        $add('session_revocation', config('session.driver') === 'database' && (! config('session.connection') || config('session.connection') === config('database.default')) && config('session.table') === 'sessions', 'Use sessions on the main database: this application revokes other sessions in that table.');
        $add('queue_database', ! config('queue.connections.database.connection') || config('queue.connections.database.connection') === config('database.default'), 'Domain jobs and jobs table must use the same database for transactional enqueue.');
        $add('queue_retry_after', (int) config('queue.connections.database.retry_after') > 45, 'Set DB_QUEUE_RETRY_AFTER above the worker/job timeout of 45 seconds (recommended 180).');
        if ($this->option('production')) {
            $add('environment', app()->environment('production'), 'Set APP_ENV=production.');
            $add('debug_off', config('app.debug') === false, 'Set APP_DEBUG=false.');
            $url = parse_url(config('app.url'));
            $add('https_origin', ($url['scheme'] ?? '') === 'https' && ! empty($url['host']) && ! in_array($url['host'], ['localhost', '127.0.0.1', 'example.com']) && ! str_ends_with($url['host'], '.example.com') && empty($url['user']) && empty($url['query']), 'Set APP_URL to the actual HTTPS origin; confirm certificate and reverse proxy separately.');
            $add('secure_sessions', (bool) config('session.secure') && config('session.http_only') && config('session.encrypt') && in_array(config('session.same_site'), ['lax', 'strict']), 'Enable secure, HTTP-only encrypted sessions and SameSite=Lax or Strict.');
            $add('email_verified', (bool) config('security.require_verified_email'), 'Require verified emails and configure delivery before opening registration.');
            $add('invitation_redis', config('workspace.invitation_store') === 'redis', 'Configure shared Redis for invitation locks and records.');
            $add('shared_cache', in_array(config('cache.default'), ['database', 'redis']), 'Use a persistent shared cache for throttles, scheduler locks and account resets.');
            $add('mail_delivery_config', config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.host')) && filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false && ! str_ends_with(config('mail.from.address'), '@example.com'), 'Configure the shipped SMTP transport and a real sender; deliver/receive a real verification mail before launch.');
            $add('config_cached', app()->configurationIsCached(), 'Run php artisan config:cache after configuring the deployment.');
            $add('routes_cached', app()->routesAreCached(), 'Run php artisan route:cache after installing the release.');
            $add('worker_timeout_support', PHP_OS_FAMILY !== 'Windows' && extension_loaded('pcntl'), 'Run production queue workers on Linux with pcntl; Windows timeout semantics are not validated.');
            $add('explicit_proxy_list', ! in_array('*', config('security.trusted_proxies')), 'Trust only known proxy IPs/CIDRs, never arbitrary forwarded headers.');
        }
        if (config('work_management.ai_enabled')) {
            $add('ai_config', str_starts_with((string) config('ai.endpoint'), 'https://') && filled(config('ai.key')), 'Configure and independently verify the AI endpoint/key before enabling it.');
        }
        if ($this->option('runtime')) {
            $names = ['scheduler', 'worker:database:'.config('ai.queue'), 'worker:database:work-management-reminders'];
            foreach ($names as $name) {
                try {
                    $stamp = (int) Cache::get('ops:heartbeat:'.$name, 0);
                } catch (\Throwable) {
                    $stamp = 0;
                }
                $add('heartbeat.'.$name, $stamp > 0 && time() - $stamp >= 0 && time() - $stamp <= 300, 'Start supervised workers and the one-minute scheduler; rerun this check.');
            }
        }
        $passed = collect($checks)->every('passed');
        $report = ['configuration_passed' => $passed, 'production_mode' => (bool) $this->option('production'), 'runtime_checked' => (bool) $this->option('runtime'), 'checks' => $checks,
            'limits' => ['This is not a production certification. Verify HTTP/TLS, actual mail receipt, backup restoration, load and third-party integrations on the target infrastructure.', 'Heartbeat freshness is recent worker activity, not proof of future availability.']];
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Check', 'Result', 'Action'], array_map(fn ($c) => [$c['check'], $c['passed'] ? 'PASS' : 'FAIL', $c['action']], $checks));
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
