<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $q = Company::query();
        if ($request->has('archived') && $request->archived == true) {
            $q->onlyTrashed();
        }
        $companies = $q->latest()->paginate(10)->withQueryString();

        return $this->successResponse(
            [
                'companies' => CompanyResource::collection($companies),
                'pagination' => [
                    'current_page' => $companies->currentPage(),
                    'last_page' => $companies->lastPage(),
                    'per_page' => $companies->perPage(),
                    'total' => $companies->total(),
                    'has_more' => $companies->hasMorePages(),
                ],
            ],
            'Companies fetched successfully',
        );
    }

    public function store(StoreCompanyRequest $request)
    {
        $validated = $request->validated();
        $company = Company::create($validated->all());

        return $this->successResponse(
            new CompanyResource($company),
            'Company created successfully.',
            201
        );
    }

    public function show(Company $company)
    {
        return $this->successResponse(
            new CompanyResource($company),
            'Company fetched successfully.',
            200
        );
    }

    public function update(UpdateCompanyRequest $request, Company $company)
    {
        $validated = $request->validated();
        $company->update($validated->all());

        return $this->successResponse(
            new CompanyResource($company),
            'Company updated successfully.',
            200
        );
    }

    public function destroy(Company $company)
    {
        $company->delete();

        return $this->successResponse(
            null,
            'Company deleted successfully.',
            204
        );
    }

    public function restore(Company $company)
    {
        $company->restore();

        return $this->successResponse(
            new CompanyResource($company),
            'Company restored successfully.',
            201
        );
    }

    public function forceDelete(Company $company)
    {
        $company->forceDelete();

        return $this->successResponse(
            null,
            'Company force deleted successfully.',
            204
        );
    }
}
