<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['sometimes', Rule::enum(SubscriptionStatus::class)],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $subscriptions = Subscription::query()
            ->with(['company', 'plan'])
            ->when(
                isset($validated['status']),
                fn ($query) => $query->where('status', $validated['status']),
            )
            ->latest()
            ->paginate($request->integer('per_page', 15))
            ->withQueryString();

        return $this->successResponse(
            [
                'subscriptions' => SubscriptionResource::collection($subscriptions),
                'pagination' => $this->pagination($subscriptions),
            ],
            'Subscriptions fetched successfully.',
        );
    }

    public function cancel(Subscription $subscription): JsonResponse
    {
        $subscription->update([
            'status' => SubscriptionStatus::CANCELED,
            'ends_at' => now(),
        ]);

        return $this->successResponse(
            new SubscriptionResource($subscription->refresh()->load(['company', 'plan'])),
            'Subscription canceled successfully.',
        );
    }
}
