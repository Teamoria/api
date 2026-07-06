<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\TaskResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $dashboard = $this->dashboardService->for($request->user());

        return $this->successResponse([
            'totals' => $dashboard['totals'],
            'project_statuses' => $dashboard['project_statuses'],
            'task_statuses' => $dashboard['task_statuses'],
            'recent_projects' => ProjectResource::collection($dashboard['recent_projects']),
            'upcoming_tasks' => TaskResource::collection($dashboard['upcoming_tasks']),
        ], 'Dashboard fetched successfully.');
    }
}
