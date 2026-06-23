<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedDecision extends Model
{
    protected $fillable = ['meeting_summary_id', 'decision_text'];

    public function meetingSummary(): BelongsTo
    {
        return $this->belongsTo(MeetingSummary::class);
    }
}
