<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class PlanController extends Controller
{
    public $config = [
        'testOrLive' => 'live',
        'fincode_secret_key' => '',
        'apiDomain' => '',
    ];
    public function __construct()
    {
        $this->config['testOrLive'] = getOption('testOrLive');

        if ($this->config['testOrLive'] =="test") {
            $this->config['fincode_secret_key'] = env('FINCODE_TEST_SECRET_KEY');
            $this->config['apiDomain'] = "https://api.test.fincode.jp";
        } else {
            $this->config['fincode_secret_key'] = env('FINCODE_LIVE_SECRET_KEY');
            $this->config['apiDomain'] = "https://api.fincode.jp";
        }
    }

    public function index()
    {
        $plans = Plan::orderBy('amount', 'asc')->get();
        return Inertia::render('Admin/Plans/Index', [
            'plans' => $plans,
            'hide_cat_bar' => 1
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Plans/Create', [
            'hide_cat_bar' => 1
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'point' => 'required|numeric|min:0'
        ]);

        // Create plan in Fincode
        $fincodeResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
        ])->post($this->config['apiDomain'] . '/v1/plans', [
            'plan_name' => $request->name,
            'description' => $request->description,
            'amount' => str($request->amount),
            'interval_pattern' => 'month',
            'interval_count' => '1',
        ]);

        if (!$fincodeResponse->successful()) {
            return back()->with('message', json_encode($fincodeResponse->json()))->with('type', 'dialog');
        }

        $fincodePlan = $fincodeResponse->json();

        // Create plan in database
        Plan::create([
            'plan_id' => $fincodePlan['id'],
            'name' => $request->name,
            'description' => $request->description,
            'amount' => $request->amount,
            'point' => $request->point
        ]);

        return redirect()->route('admin.plans.index')->with('message', 'プランを作成しました')->with('type', 'dialog');
    }

    public function edit(Plan $plan)
    {
        return Inertia::render('Admin/Plans/Edit', [
            'plan' => $plan,
            'hide_cat_bar' => 1
        ]);
    }

    public function update(Request $request, Plan $plan)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
            'point' => 'required|numeric|min:0'
        ]);

        if ($plan->name != $request->name
            || $plan->description != $request->description
            || $plan->amount != $request->amount) {
            // Update plan in Fincode
            $fincodeResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
            ])->put($this->config['apiDomain'] . '/v1/plans/' . $plan->plan_id, [
                'plan_name' => $request->name,
                'description' => $request->description,
                'amount' => str($request->amount),
            ]);
            if (!$fincodeResponse->successful()) {
                return back()->with('message', 'Fincodeでのプラン更新に失敗しました')->with('type', 'dialog');
            }
        }

        // Update plan in database
        $plan->update([
            'name' => $request->name,
            'description' => $request->description,
            'amount' => $request->amount,
            'point' => $request->point
        ]);

        return redirect()->back()->with('message', 'プランを更新しました')->with('type', 'dialog');
    }

    public function destroy(Plan $plan)
    {
        // Delete plan in Fincode
        $fincodeResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
        ])->delete($this->config['apiDomain'] . '/v1/plans/' . $plan->plan_id);

        if (!$fincodeResponse->successful()) {
            return back()->with('message', 'Fincodeでのプラン削除に失敗しました')->with('type', 'dialog');
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('message', 'プランを削除しました')->with('type', 'dialog');
    }
}
