<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptSegment extends Model
{
    use HasUuids;

    protected $table = 'transcript_segments';

    public $timestamps = false;
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'transcript_id',
        'sequence_number',
        'speaker_label',
        'start_ms',
        'end_ms',
        'text',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'start_ms' => 'integer',
            'end_ms' => 'integer',
        ];
    }

    public function transcript(): BelongsTo
    {
        return $this->belongsTo(Transcript::class, 'transcript_id');
    }
}
