<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtractedTask extends Model
{
    use HasUuids;

    protected $fillable = ['meeting_summary_id', 'task_text'];

    public function meetingSummary(): BelongsTo
    {
        return $this->belongsTo(MeetingSummary::class);
    }
}
