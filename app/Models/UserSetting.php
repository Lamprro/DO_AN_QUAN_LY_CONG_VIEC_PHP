<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends DomainModel
{
    protected $fillable = ['user_id', 'settings'];

    protected $hidden = [];

    protected $attributes = [
        'settings' => '{}',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
