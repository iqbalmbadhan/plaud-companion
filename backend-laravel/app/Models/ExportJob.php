<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExportJob extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'export_jobs';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'requested_by_user_id',
        'status',
        'template',
        'settings',
        'total_items',
        'completed_items',
        'failed_items',
        'archive_storage_key',
        'manifest_storage_key',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'total_items' => 'integer',
            'completed_items' => 'integer',
            'failed_items' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ExportJobItem::class, 'export_job_id');
    }
}
