<?php

namespace App\Support;

class MetricDisplay
{
    public static function minutes(int|float|null $minutes): string
    {
        if ($minutes === null) {
            return 'Chưa có dữ liệu';
        }
        $seconds = (int) round($minutes * 60);
        $hours = intdiv($seconds, 3600);
        $mins = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        if ($hours) {
            return $hours.' giờ'.($mins ? ' '.$mins.' phút' : '').($secs ? ' '.$secs.' giây' : '');
        }
        if ($mins) {
            return $mins.' phút'.($secs ? ' '.$secs.' giây' : '');
        }

        return $seconds === 0 ? '0 phút' : $secs.' giây';
    }
}
