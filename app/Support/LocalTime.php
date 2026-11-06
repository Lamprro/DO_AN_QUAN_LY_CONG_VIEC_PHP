<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/** Parse a wall-clock value entered in a timezone into UTC, rejecting non-existent / repeated DST minutes. */
final class LocalTime
{
    public static function parse(string $value, string $timezone, bool $allDay = false): CarbonImmutable
    {
        $format = $allDay ? 'Y-m-d' : 'Y-m-d\TH:i';
        try {
            $time = CarbonImmutable::createFromFormat('!'.$format, $value, $timezone);
        } catch (\Throwable) {
            self::fail('Ngày giờ không hợp lệ.');
        }
        if ($time->format($format) !== $value) {
            self::fail('Giờ không tồn tại trong múi giờ này (DST).');
        }
        // datetime-local has no UTC offset: reject repeated DST minutes rather than silently selecting one.
        if (! $allDay) {
            $zone = new \DateTimeZone($timezone);
            $wall = CarbonImmutable::createFromFormat('!'.$format, $value, 'UTC')->timestamp;
            $transitions = $zone->getTransitions($time->timestamp - 86400, $time->timestamp + 86400);
            $prior = $transitions[0]['offset'] ?? 0;
            foreach (array_slice($transitions ?: [], 1) as $transition) {
                if ($transition['offset'] < $prior && $wall >= $transition['ts'] + $transition['offset'] && $wall < $transition['ts'] + $prior) {
                    self::fail('Giờ bị lặp do DST; chọn giờ khác hoặc nhập theo UTC.');
                }
                $prior = $transition['offset'];
            }
        }

        return $time->utc();
    }

    private static function fail(string $text): never
    {
        throw ValidationException::withMessages(['starts_at' => $text]);
    }
}
