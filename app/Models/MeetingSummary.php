<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingSummary extends Model
{
    use HasUuids;

    protected $fillable = [
        'upload_id',
        'source_type',
        'transcript',
        'transcript_quality',
        'summary',
        'structured_summary',
        'indexed_chunk_count',
    ];

    protected function casts(): array
    {
        return [
            'structured_summary' => 'array',
            'transcript_quality' => 'array',
            'indexed_chunk_count' => 'integer',
        ];
    }

    public function upload(): BelongsTo
    {
        return $this->belongsTo(Upload::class);
    }

    public function extractedDecisions(): HasMany
    {
        return $this->hasMany(ExtractedDecision::class);
    }

    public function extractedTasks(): HasMany
    {
        return $this->hasMany(ExtractedTask::class);
    }
}
