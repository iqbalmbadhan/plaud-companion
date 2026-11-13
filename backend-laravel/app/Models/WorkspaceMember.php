<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMember extends Model
{
    use HasUuids;

    protected $table = 'workspace_members';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'user_id',
        'role',
        'status',
        'joined_at',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function canManageMembers(): bool
    {
        return in_array($this->role, ['owner', 'admin'], true);
    }

    public function canEditTranscripts(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'editor'], true);
    }

    public function canExport(): bool
    {
        return in_array($this->role, ['owner', 'admin', 'editor'], true);
    }
}
