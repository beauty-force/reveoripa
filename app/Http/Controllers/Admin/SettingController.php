<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Str;

class SettingController extends Controller
{
    public function index() {
        $maintainance = getOption('maintainance');
        $testOrLive = getOption('testOrLive');
        $is3DSecure = getOption('is3DSecure');
        $invite_bonus = getOption('invite_bonus');
        if ($invite_bonus == '') $invite_bonus = '0';
        $invite_bonus = intval($invite_bonus);

        $invited_bonus = getOption('invited_bonus');
        if ($invited_bonus == '') $invited_bonus = '0';
        $invited_bonus = intval($invited_bonus);
        
        $delivery_limit = getOption('delivery_limit');
        $delivery_limit = intval($delivery_limit == "" ? "1000" : $delivery_limit);

        $hide_cat_bar = 1;
        return inertia('Admin/Settings/Index', compact('maintainance', 'hide_cat_bar', 'testOrLive', 'is3DSecure', 'invite_bonus', 'invited_bonus', 'delivery_limit'));
    }

    public function maintenance_store(Request $request) {
        $maintainance = $request->maintainance;
        setOption('maintainance', $maintainance);
        return redirect()->back()->with('message', '保存しました。')->with('title', '設定')->with('type', 'dialog');
    }

    public function payment_store(Request $request) {
        if (isset($request->testOrLive)) {
            $testOrLive = $request->testOrLive;
            setOption('testOrLive', $testOrLive);
        }
        if (isset($request->is3DSecure)) {
            $is3DSecure = $request->is3DSecure;
            setOption('is3DSecure', $is3DSecure);
        }
        if (isset($request->invite_bonus)) {
            $invite_bonus = $request->invite_bonus;
            setOption('invite_bonus', $invite_bonus);
        }
        if (isset($request->invited_bonus)) {
            $invited_bonus = $request->invited_bonus;
            setOption('invited_bonus', $invited_bonus);
        }
        if (isset($request->delivery_limit)) {
            $delivery_limit = $request->delivery_limit;
            setOption('delivery_limit', $delivery_limit);
        }
        return redirect()->back()->with('message', '保存しました。')->with('title', '設定')->with('type', 'dialog');
    }


}
