<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasUuids, SoftDeletes;

    protected $table = 'users';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'display_name',
        'password_hash',
        'status',
        'is_super_admin',
        'is_developer',
        'email_verified_at',
        'sessions_valid_after',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_super_admin' => 'boolean',
            'is_developer' => 'boolean',
            'email_verified_at' => 'datetime',
            'sessions_valid_after' => 'datetime',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /** Developers have every super-admin power plus updates, impersonation and the debug log. */
    public function isDeveloper(): bool
    {
        return $this->is_developer === true;
    }

    public function hasAdminAccess(): bool
    {
        return $this->is_super_admin === true || $this->is_developer === true;
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'user_id');
    }

    public function ownedWorkspaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_user_id');
    }
}
