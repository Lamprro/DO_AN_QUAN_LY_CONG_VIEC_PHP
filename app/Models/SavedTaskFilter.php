<?php

namespace App\Models;

class SavedTaskFilter extends DomainModel
{
    protected $fillable = ['user_id', 'workspace_id', 'project_id', 'name', 'query'];

    protected function casts(): array
    {
        return ['query' => 'array'];
    }
}
