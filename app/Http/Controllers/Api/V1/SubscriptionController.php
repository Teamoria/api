<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PlanStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Billing\SubscribeRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function indexPlans(): JsonResponse
    {
        $plans = Plan::query()
            ->where('status', PlanStatus::ACTIVE->value)
            ->latest()
            ->get();

        return $this->successResponse(
            ['plans' => PlanResource::collection($plans)],
            'Plans fetched successfully.',
        );
    }

    public function mySubscription(Request $request): JsonResponse
    {
        $company = $request->user()?->company;

        if ($company === null) {
            return $this->errorResponse(
                'You must be assigned to a company to view subscription details.',
                403,
            );
        }

        $subscription = $company->activeSubscription()?->loadMissing(['company', 'plan']);

        return $this->successResponse(
            ['subscription' => $subscription === null ? null : new SubscriptionResource($subscription)],
            $subscription === null
                ? 'No active subscription found.'
                : 'Subscription fetched successfully.',
        );
    }

    public function subscribe(SubscribeRequest $request): JsonResponse
    {
        $company = $request->user()?->company;

        if ($company === null) {
            return $this->errorResponse(
                'You must be assigned to a company to subscribe to a plan.',
                403,
            );
        }

        $billingCycle = $request->billingCycle();
        $plan = Plan::query()
            ->where('status', PlanStatus::ACTIVE->value)
            ->findOrFail($request->planId());

        $payment = DB::transaction(function () use ($company, $plan, $billingCycle, $request): Payment {
            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'status' => SubscriptionStatus::CANCELED,
            ]);

            $payment = Payment::create([
                'subscription_id' => $subscription->id,
                'company_id' => $company->id,
                'amount' => $this->amountFor($plan, $billingCycle),
                'method' => PaymentMethod::BANK_TRANSFER,
                'status' => PaymentStatus::PENDING,
                'reference_number' => $request->referenceNumber(),
            ]);

            return $payment->load(['company', 'subscription.plan']);
        });

        return $this->successResponse(
            ['payment' => new PaymentResource($payment)],
            'Subscription request created successfully. Your payment is pending admin verification.',
            201,
        );
    }

    private function amountFor(Plan $plan, BillingCycle $billingCycle): string
    {
        return match ($billingCycle) {
            BillingCycle::MONTHLY => $plan->price_monthly,
            BillingCycle::YEARLY => $plan->price_yearly,
        };
    }
}
