<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company' => $this->whenLoaded('company', fn () => new CompanyResource($this->company)),
            'subscription' => $this->whenLoaded('subscription', fn () => new SubscriptionResource($this->subscription)),
            'amount' => $this->amount,
            'method' => $this->method,
            'status' => $this->status,
            'reference_number' => $this->reference_number,
            'notes' => $this->notes,
            'paid_at' => $this->paid_at,
            'confirmed_at' => $this->confirmed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
