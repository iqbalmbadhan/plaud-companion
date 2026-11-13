<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJobItem extends Model
{
    use HasUuids;

    protected $table = 'export_job_items';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'export_job_id',
        'transcript_id',
        'status',
        'output_storage_key',
        'error_code',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ExportJob::class, 'export_job_id');
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class, 'transcript_id');
    }
}
