<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaudAccountLink extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'user_id', 'workspace_id', 'access_token', 'refresh_token', 'expires_at',
        'status', 'last_error', 'last_synced_at', 'imported_count',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'imported_count' => 'integer',
        ];
    }
}
