<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\UserSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Payment;

class SubscriptionController extends Controller
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
        $currentSubscription = auth()->user()->activeSubscription;

        if ($currentSubscription) {
            $currentSubscription->plan = $currentSubscription->plan;
        }

        $cards = [];

        if (auth()->user()->customer_id) {
            // Get user's cards from Fincode
            $fincodeResponse = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
            ])->get($this->config['apiDomain'] . '/v1/customers/' . auth()->user()->customer_id . '/cards');

            $cards = $fincodeResponse->successful() ? $fincodeResponse->json()['list'] : [];
        }
        
        return Inertia::render('User/Subscription/Index', [
            'plans' => $plans,
            'currentSubscription' => $currentSubscription,
            'cards' => $cards,
            'hide_cat_bar' => 1,
        ]);
    }

    public function subscribe(Request $request, Plan $plan)
    {
        $request->validate([
            'card_id' => 'required|string'
        ]);
        $activeSubscription = auth()->user()->activeSubscription;
        if ($activeSubscription) {
            return redirect()->back()->with('message', 'サブスクリプションがアクティブです')->with('type', 'dialog_error');
        }
        $last_subscription = UserSubscription::where('user_id', auth()->user()->id)
        ->orderBy('stop_date', 'desc')->first();

        $start_date = now()->format('Y/m/d');
        if ($last_subscription) {
            $min_date = $last_subscription->stop_date->addDays(30)->startOfDay();
            if ($min_date > now()) {
                return redirect()->back()->with('message', $min_date->format('Y年n月j日').'以降に登録できます。')->with('type', 'dialog_error');
            }
        }

        // Create subscription in Fincode
        $fincodeResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
        ])->post($this->config['apiDomain'] . '/v1/subscriptions', [
            'pay_type' => 'Card',
            'plan_id' => $plan->plan_id,
            'customer_id' => auth()->user()->customer_id,
            'card_id' => $request->card_id,
            'start_date' => $start_date,
            'client_field_1' => str(auth()->user()->id),
            'client_field_2' => str($plan->id),
            'client_field_3' => $start_date,
        ]);

        if (!$fincodeResponse->successful()) {
            return redirect()->back()->with('message', $fincodeResponse->json()["errors"][0]["error_message"])->with('type', 'dialog_error');
        }

        return redirect()->route('user.subscription.index')->with('message', 'サブスクリプションに登録されました。<br/>課金開始日は'.$start_date.'です。')->with('type', 'dialog_success');
    }

    public function update(Request $request, UserSubscription $subscription)
    {
        $request->validate([
            'plan_id' => 'required|numeric',
            'card_id' => 'required|string'
        ]);

        $old_plan = $subscription->plan;
        $plan = Plan::find($request->plan_id);

        if (!$plan) {
            return redirect()->back()->with('message', 'プランが見つかりません')->with('type', 'dialog_error');
        }

        if ($subscription->plan_id == $request->plan_id && $subscription->card_id == $request->card_id) {
            return redirect()->back()->with('message', 'プランが変更されていません')->with('type', 'dialog');
        }

        $fincodeResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
        ])->get($this->config['apiDomain'] . '/v1/subscriptions/' . $subscription->subscription_id . '?pay_type=Card');
        
        $fincodeSubscription = $fincodeResponse->json();
        if ($fincodeSubscription['status'] != 'ACTIVE') {
            return redirect()->back()->with('message', 'サブスクリプションがアクティブではありません')->with('type', 'dialog_error');
        }
        
        $requestParams = [
            'pay_type' => 'Card',
            'plan_id' => $plan->plan_id,
            'customer_id' => auth()->user()->customer_id,
            'card_id' => $request->card_id,
            'client_field_1' => str(auth()->user()->id),
            'client_field_2' => str($plan->id),
        ];

        $initial_amount = max(0, $plan->amount - $old_plan->amount);
        $start_date = now()->format('Y/m/d');
        if ($initial_amount > 0) {
            $requestParams['initial_amount'] = str($initial_amount);
            $requestParams['start_date'] = $start_date;
        }
        else {
            $requestParams['start_date'] = (new \DateTime($fincodeSubscription['next_charge_date']))->format('Y/m/d');
            $start_date = $subscription->start_date;
        }
        $requestParams['client_field_3'] = $start_date;

        // Create subscription in Fincode
        $fincodeResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
        ])->post($this->config['apiDomain'] . '/v1/subscriptions', $requestParams);

        if (!$fincodeResponse->successful()) {
            return redirect()->back()->with('message', $fincodeResponse->json()["errors"][0]["error_message"])->with('type', 'dialog_error');
        }

        $deleteResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
        ])->delete($this->config['apiDomain'] . '/v1/subscriptions/' . $subscription->subscription_id . '?pay_type=Card');

        // Update subscription in database
        $subscription->update([
            'status' => 'cancelled',
            'stop_date' => now()
        ]);
        
        return redirect()->route('user.subscription.index')->with('message', 'サブスクリプションを更新しました')->with('type', 'dialog_success');
    }

    public function cancel(UserSubscription $subscription)
    {
        if ($subscription->status != 'active') {
            return redirect()->back()->with('message', 'サブスクリプションがアクティブではありません')->with('type', 'dialog_error');
        }
        // Cancel subscription in Fincode
        $fincodeResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->config['fincode_secret_key']
        ])->delete($this->config['apiDomain'] . '/v1/subscriptions/' . $subscription->subscription_id . '?pay_type=Card');

        if (!$fincodeResponse->successful()) {
            return redirect()->back()->with('message', $fincodeResponse->json()["errors"][0]["error_message"])->with('type', 'dialog_error');
        }

        // Update subscription in database
        $subscription->update([
            'status' => 'cancelled',
            'stop_date' => now()
        ]);
        auth()->user()->update([
            'current_plan' => 0
        ]);

        return redirect()->route('user.subscription.index')->with('message', 'サブスクリプションをキャンセルしました')->with('type', 'dialog_success');
    }
}
