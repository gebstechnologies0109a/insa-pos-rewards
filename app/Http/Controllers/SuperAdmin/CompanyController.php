<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\POS\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {
        $companies = Company::withCount('branches')
            ->orderBy('name')
            ->get();

        return view('super-admin.companies.index', compact('companies'));
    }

    public function create(): View
    {
        return view('super-admin.companies.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255|unique:companies,name',
            'status' => 'required|in:active,inactive',
        ]);

        Company::create($data);

        return redirect()->route('super-admin.companies.index')
            ->with('success', 'Company created successfully.');
    }

    public function edit(Company $company): View
    {
        return view('super-admin.companies.edit', compact('company'));
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $data = $request->validate([
            'name'   => 'required|string|max:255|unique:companies,name,' . $company->id,
            'status' => 'required|in:active,inactive',
        ]);

        $company->update($data);

        return redirect()->route('super-admin.companies.index')
            ->with('success', 'Company updated successfully.');
    }
}
