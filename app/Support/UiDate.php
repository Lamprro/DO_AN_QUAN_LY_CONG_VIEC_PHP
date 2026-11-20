<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * One display format for dates everywhere on screen: dd/mm/yyyy (and dd/mm/yyyy HH:mm).
 * Form values sent to the server stay ISO (Y-m-d, Y-m-d\TH:i); public/js/date-inputs.js
 * shows those inputs as dd/mm/yyyy too.
 */
class UiDate
{
    public const DAY = 'd/m/Y';

    public const DAY_TIME = 'd/m/Y H:i';

    /** "2026-11-16" -> "16/11/2026"; anything unparsable is returned unchanged. */
    public static function day(?string $ymd): string
    {
        if (! $ymd) {
            return '';
        }
        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $ymd)->format(self::DAY);
        } catch (\Throwable) {
            return $ymd;
        }
    }
}
