<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'workspaces';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'type',
        'owner_user_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class, 'workspace_id');
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(WorkspaceInvitation::class, 'workspace_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'workspace_id');
    }

    public function transcripts(): HasMany
    {
        return $this->hasMany(Transcript::class, 'workspace_id');
    }

    public function exportJobs(): HasMany
    {
        return $this->hasMany(ExportJob::class, 'workspace_id');
    }

    public function providerConnections(): HasMany
    {
        return $this->hasMany(ProviderConnection::class, 'workspace_id');
    }
}
