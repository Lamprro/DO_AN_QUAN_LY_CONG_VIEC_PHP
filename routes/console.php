<?php

use App\Services\AiRunService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('ai:recover-stale', function () {
    $this->info('Recovered stale AI runs: '.app(AiRunService::class)->recoverStale());
})->purpose('Mark abandoned running AI requests failed without generating fake results');
