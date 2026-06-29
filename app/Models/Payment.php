<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasUuids;
    protected $fillable = [
        'subscription_id',
        'company_id',
        'amount',
        'method',
        'status',
        'reference_number',
        'notes',
        'paid_at',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
