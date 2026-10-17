<?php

namespace App\Models\Concerns;

trait HasEditRevision
{
    public static function bootHasEditRevision(): void
    {
        static::updating(function ($model) {
            if ($model->isDirty()) {
                $model->setAttribute('revision', (int) $model->getRawOriginal('revision') + 1);
            }
        });
    }
}
