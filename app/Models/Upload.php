<?php

namespace App\Models;

use App\Enums\FileCategory;
use App\Enums\UploadStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Upload extends Model
{
    use HasUuids;

    protected $fillable = [
        'project_id',
        'user_id',
        'file_path',
        'file_name',
        'file_type',
        'category',
        'file_size',
        'status',
        'upload_date',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'category' => FileCategory::class,
            'status' => UploadStatus::class,
            'upload_date' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function meetingSummary(): HasOne
    {
        return $this->hasOne(MeetingSummary::class);
    }

    public function knowledgeChunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }
}
