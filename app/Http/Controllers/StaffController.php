<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Staff\StoreStaffRequest;
use App\Http\Requests\Staff\UpdateStaffRequest;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $q = User::query()->where('company_id', $request->user()->company_id);

        if ($request->has('archived') && $request->archived == true) {
            $q->onlyTrashed();
        }
        if ($request->has('roles') && count($request->roles)) {
            $q->whereIn('role', $request->roles);
        }
        if ($request->has('statuses') && count($request->statuses)) {
            $q->whereIn('status', $request->statuses);
        }

        $users = $q->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'users' => UserResource::collection($users),
                'company' => new CompanyResource($request->user()->company),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'has_more' => $users->hasMorePages(),
                ],
            ],
            'Users fetched successfully',
        );
    }

    public function store(StoreStaffRequest $request)
    {
        $validated = $request->validated();
        $validated['company_id'] = $request->user()->company_id;
        $user = User::create($validated);

        return $this->successResponse(
            new UserResource($user),
            'User created successfully.',
            201
        );
    }

    public function show(Request $request, string $id)
    {
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);

        return $this->successResponse(
            new UserResource($user),
            'User fetched successfully.',
            200
        );
    }

    public function update(UpdateStaffRequest $request, string $id)
    {
        $validated = $request->validated();
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);
        $user->update($validated);

        return $this->successResponse(
            new UserResource($user),
            'User updated successfully.',
            200
        );
    }

    public function destroy(Request $request, string $id)
    {
        $user = User::where('company_id', $request->user()->company_id)->findOrFail($id);
        $user->delete();

        return $this->successResponse(
            null,
            'User deleted successfully.',
            200
        );
    }

    public function restore(Request $request, string $id)
    {
        $user = User::withTrashed()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);
        $user->restore();

        return $this->successResponse(
            new UserResource($user),
            'User restored successfully.',
            200
        );
    }

    public function forceDelete(Request $request, string $id)
    {
        $user = User::withTrashed()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);
        $user->forceDelete();

        return $this->successResponse(
            null,
            'User force deleted successfully.',
            200
        );
    }
}
