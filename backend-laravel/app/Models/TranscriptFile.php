<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptFile extends Model
{
    use HasUuids;

    protected $table = 'transcript_files';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'transcript_id',
        'workspace_id',
        'storage_key',
        'original_filename',
        'mime_type',
        'size_bytes',
        'sha256',
        'file_role',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class, 'transcript_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class, 'workspace_id');
    }
}
