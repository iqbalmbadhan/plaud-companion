<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transcript extends Model
{
    use HasUuids, SoftDeletes;

    protected $table = 'transcripts';

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'workspace_id',
        'project_id',
        'created_by_user_id',
        'title',
        'description',
        'source_type',
        'recorded_at',
        'language',
        'status',
        'content_text',
        'content_hash',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TranscriptSegment::class, 'transcript_id')
            ->orderBy('sequence_number');
    }

    public function files(): HasMany
    {
        return $this->hasMany(TranscriptFile::class, 'transcript_id');
    }

    public function exportJobItems(): HasMany
    {
        return $this->hasMany(ExportJobItem::class, 'transcript_id');
    }
}
