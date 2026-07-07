<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedDecision extends Model
{
    use HasUuids;

    protected $fillable = [
        'meeting_summary_id',
        'decision_text',
        'title',
        'description',
        'confidence',
    ];

    public function meetingSummary(): BelongsTo
    {
        return $this->belongsTo(MeetingSummary::class);
    }
}
