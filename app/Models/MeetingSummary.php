<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeetingSummary extends Model
{
    use HasUuids;

    protected $fillable = ['upload_id', 'transcript', 'summary'];

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
