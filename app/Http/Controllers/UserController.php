<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = User::with('company');
        if ($request->has('archived')) {
            $q->onlyTrashed();
        }
        $users = $q->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'users' => UserResource::collection($users),
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
}
