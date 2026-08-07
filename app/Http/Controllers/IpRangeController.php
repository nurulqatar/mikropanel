<?php

namespace App\Http\Controllers;

use App\Models\IpRange;
use App\Models\Router;
use App\Http\Requests\IpRangeRequest;
use Inertia\Inertia;
use Illuminate\Http\RedirectResponse;

class IpRangeController extends Controller
{
    public function index()
    {
        return Inertia::render('IpRanges/Index', [
            'ranges' => IpRange::with('router')->latest()->get(),
        ]);
    }


    public function create()
    {
        return Inertia::render('IpRanges/Create', [
            'routers' => Router::where('enabled', true)->get(),
        ]);
    }


    public function store(IpRangeRequest $request): RedirectResponse
    {
        IpRange::create(
            $request->validated()
        );

        return redirect()
            ->route('ip-ranges.index')
            ->with('success', 'IP Range created.');
    }


    public function edit(IpRange $ipRange)
    {
        return Inertia::render('IpRanges/Edit', [
            'range' => $ipRange,
            'routers' => Router::where('enabled', true)->get(),
        ]);
    }


    public function update(
        IpRangeRequest $request,
        IpRange $ipRange
    ): RedirectResponse {

        $ipRange->update(
            $request->validated()
        );

        return redirect()
            ->route('ip-ranges.index')
            ->with('success', 'IP Range updated.');
    }


    public function destroy(IpRange $ipRange): RedirectResponse
    {
        if ($ipRange->clients()->exists()) {

            return back()
                ->with(
                    'error',
                    'Cannot delete IP Range with active clients.'
                );
        }

        $ipRange->delete();

        return back()
            ->with(
                'success',
                'IP Range deleted.'
            );
    }
}
