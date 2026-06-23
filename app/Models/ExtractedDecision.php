<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExtractedDecision extends Model
{
    protected $fillable = ['meeting_summary_id', 'decision_text'];

    public function meetingSummary()
    {
        return $this->belongsTo(MeetingSummary::class);
    }
}
