<?php

namespace App\Http\Requests\Billing;

use App\Enums\BillingCycle;
use App\Enums\PlanStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscribeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'plan_id' => [
                'required',
                'uuid',
                Rule::exists('plans', 'id')->where('status', PlanStatus::ACTIVE->value),
            ],
            'billing_cycle' => ['required', Rule::enum(BillingCycle::class)],
            'reference_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function planId(): string
    {
        return $this->validated('plan_id');
    }

    public function billingCycle(): BillingCycle
    {
        return BillingCycle::from($this->validated('billing_cycle'));
    }

    public function referenceNumber(): ?string
    {
        return $this->validated('reference_number');
    }
}
