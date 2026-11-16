<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEvent extends Model
{
    use HasUuids;

    protected $table = 'audit_events';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'actor_user_id',
        'action',
        'resource_type',
        'resource_id',
        'metadata',
        'created_at',
        'impersonator_user_id',
    ];

    protected static function booted(): void
    {
        // Every audit entry written while a developer is impersonating names the developer too.
        static::creating(function (AuditEvent $event): void {
            if ($event->impersonator_user_id === null) {
                $event->impersonator_user_id = \App\Support\Impersonation::impersonatorId();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
