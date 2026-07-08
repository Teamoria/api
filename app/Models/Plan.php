<?php

namespace App\Models;

use App\Enums\PlanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasUuids;

    protected $attributes = [
        'price_monthly' => 0,
        'price_yearly' => 0,
        'max_projects' => 5,
        'max_members' => 10,
        'max_storage_mb' => 500,
        'has_ai_features' => false,
        'status' => PlanStatus::ACTIVE->value,
    ];

    protected $fillable = [
        'name',
        'description',
        'price_monthly',
        'price_yearly',
        'max_projects',
        'max_members',
        'max_storage_mb',
        'has_ai_features',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'price_yearly' => 'decimal:2',
            'max_projects' => 'integer',
            'max_members' => 'integer',
            'max_storage_mb' => 'integer',
            'has_ai_features' => 'boolean',
            'status' => PlanStatus::class,
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
