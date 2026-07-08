<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PlanStatus;
use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Billing\StorePlanRequest;
use App\Http\Requests\Billing\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;

class AdminPlanController extends Controller
{
    public function index(): JsonResponse
    {
        $plans = Plan::query()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return $this->successResponse(
            [
                'plans' => PlanResource::collection($plans),
                'pagination' => $this->pagination($plans),
            ],
            'Plans fetched successfully.',
        );
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        $plan = Plan::query()->create([
            ...$request->validated(),
            'status' => PlanStatus::ACTIVE,
        ]);

        return $this->successResponse(
            new PlanResource($plan),
            'Plan created successfully.',
            201,
        );
    }

    public function show(Plan $plan): JsonResponse
    {
        return $this->successResponse(
            new PlanResource($plan),
            'Plan fetched successfully.',
        );
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        $plan->update($request->validated());

        return $this->successResponse(
            new PlanResource($plan->refresh()),
            'Plan updated successfully.',
        );
    }

    public function destroy(Plan $plan): JsonResponse
    {
        $plan->update([
            'status' => PlanStatus::ARCHIVED,
        ]);

        return $this->successResponse(
            new PlanResource($plan->refresh()),
            'Plan archived successfully.',
        );
    }
}
