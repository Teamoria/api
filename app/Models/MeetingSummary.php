<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeetingSummary extends Model
{
    protected $fillable = ['upload_id', 'transcript', 'summary'];

    public function upload()
    {
        return $this->belongsTo(Upload::class);
    }

    public function extractedDecisions()
    {
        return $this->hasMany(ExtractedDecision::class);
    }
}
