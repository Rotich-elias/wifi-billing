<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount('vouchers')->latest()->paginate(15);
        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:daily,weekly,monthly',
            'duration_hours'  => 'required|integer|min:1',
            'price'           => 'required|numeric|min:0',
            'bandwidth_up'    => 'required|string|max:20',
            'bandwidth_down'  => 'required|string|max:20',
            'session_timeout' => 'nullable|integer|min:0',
            'idle_timeout'    => 'nullable|integer|min:0',
            'description'     => 'nullable|string',
            'is_active'       => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        Plan::create($data);

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan created successfully.');
    }

    public function show(Plan $plan)
    {
        $plan->loadCount('vouchers');
        $vouchers = $plan->vouchers()->with('payment')->latest()->paginate(20);
        return view('admin.plans.show', compact('plan', 'vouchers'));
    }

    public function edit(Plan $plan)
    {
        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:100',
            'type'            => 'required|in:daily,weekly,monthly',
            'duration_hours'  => 'required|integer|min:1',
            'price'           => 'required|numeric|min:0',
            'bandwidth_up'    => 'required|string|max:20',
            'bandwidth_down'  => 'required|string|max:20',
            'session_timeout' => 'nullable|integer|min:0',
            'idle_timeout'    => 'nullable|integer|min:0',
            'description'     => 'nullable|string',
        ]);

        $data['is_active'] = $request->boolean('is_active', true);
        $plan->update($data);

        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan updated successfully.');
    }

    public function destroy(Plan $plan)
    {
        if ($plan->vouchers()->whereIn('status', ['unused', 'active'])->exists()) {
            return back()->with('error', 'Cannot delete plan with active or unused vouchers.');
        }

        $plan->delete();
        return redirect()->route('admin.plans.index')
            ->with('success', 'Plan deleted.');
    }
}
