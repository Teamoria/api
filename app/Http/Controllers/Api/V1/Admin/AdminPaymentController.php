<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\BillingCycle;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminPaymentController extends Controller
{
    public function index(): JsonResponse
    {
        $payments = Payment::query()
            ->with(['company', 'subscription.plan'])
            ->where('status', PaymentStatus::PENDING->value)
            ->latest()
            ->get();

        return $this->successResponse(
            ['payments' => PaymentResource::collection($payments)],
            'Pending payments fetched successfully.',
        );
    }

    public function confirm(Payment $payment): JsonResponse
    {
        if ($payment->status === PaymentStatus::COMPLETED) {
            return $this->errorResponse('This payment has already been completed.', 409);
        }

        $payment = DB::transaction(function () use ($payment): Payment {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::COMPLETED) {
                throw ApiException::conflict('This payment has already been completed.');
            }

            $startsAt = now();
            $payment->update([
                'status' => PaymentStatus::COMPLETED,
                'confirmed_at' => $startsAt,
            ]);

            /** @var Subscription $subscription */
            $subscription = $payment->subscription()
                ->lockForUpdate()
                ->firstOrFail();

            $billingPeriodStartsAt = $subscription->ends_at?->isFuture() === true
                ? $subscription->ends_at->copy()
                : $startsAt->copy();

            $subscription->update([
                'status' => SubscriptionStatus::ACTIVE,
                'starts_at' => $startsAt,
                'ends_at' => match ($subscription->billing_cycle) {
                    BillingCycle::MONTHLY => $billingPeriodStartsAt->addMonth(),
                    BillingCycle::YEARLY => $billingPeriodStartsAt->addYear(),
                },
            ]);

            return $payment->load(['company', 'subscription.plan']);
        });

        return $this->successResponse(
            ['payment' => new PaymentResource($payment)],
            'Payment confirmed successfully.',
        );
    }
}
