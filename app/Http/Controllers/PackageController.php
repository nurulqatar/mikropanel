<?php

namespace App\Http\Controllers;

use App\Http\Requests\PackageRequest;
use App\Models\Package;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Packages/Index', [
            'packages' => Package::latest()->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Packages/Create');
    }

    public function store(PackageRequest $request): RedirectResponse
    {
        Package::create($request->validated());

        return redirect()
            ->route('packages.index')
            ->with('success', 'Package created successfully.');
    }

    public function show(Package $package): RedirectResponse
    {
        return redirect()->route('packages.index');
    }

    public function edit(Package $package): Response
    {
        return Inertia::render('Packages/Edit', [
            'package' => $package,
        ]);
    }

    public function update(
        PackageRequest $request,
        Package $package
    ): RedirectResponse {

        $package->update($request->validated());

        return redirect()
            ->route('packages.index')
            ->with('success', 'Package updated successfully.');
    }

    public function destroy(Package $package): RedirectResponse
    {
        $package->delete();

        return back()->with('success', 'Package deleted.');
    }
}
