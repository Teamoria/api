<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companiesQuery = Company::query();

        if ($request->boolean('archived')) {
            $companiesQuery->onlyTrashed();
        }

        $companies = $companiesQuery->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'companies' => CompanyResource::collection($companies),
                'pagination' => $this->pagination($companies),
            ],
            'Companies fetched successfully',
        );
    }

    public function store(StoreCompanyRequest $request): JsonResponse
    {
        $company = Company::create($request->validated());

        return $this->successResponse(
            new CompanyResource($company),
            'Company created successfully.',
            201
        );
    }

    public function show(Company $company): JsonResponse
    {
        return $this->successResponse(
            new CompanyResource($company),
            'Company fetched successfully.',
            200
        );
    }

    public function update(UpdateCompanyRequest $request, string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->update($request->validated());

        return $this->successResponse(
            new CompanyResource($company),
            'Company updated successfully.',
            200
        );
    }

    public function destroy(string $id): JsonResponse
    {
        $company = Company::findOrFail($id);
        $company->delete();

        return $this->successResponse(
            null,
            'Company deleted successfully.',
            200
        );
    }

    public function restore(string $id): JsonResponse
    {
        $company = Company::withTrashed()->findOrFail($id);
        $company->restore();

        return $this->successResponse(
            new CompanyResource($company),
            'Company restored successfully.',
            201
        );
    }

    public function forceDelete(string $id): JsonResponse
    {
        $company = Company::withTrashed()->findOrFail($id);
        $company->forceDelete();

        return $this->successResponse(
            null,
            'Company force deleted successfully.',
            200
        );
    }
}
