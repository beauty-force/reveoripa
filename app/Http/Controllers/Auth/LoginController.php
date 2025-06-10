<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Models\Verify;
use App\Models\User;


class LoginController extends Controller
{
    public function create()
    {
        if (auth()->user()) {
            return redirect()->route('main');
        }
        $hide_cat_bar = 1;
        return Inertia::render('Auth/LoginPhone', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'hide_cat_bar' =>1 ,
            'redirect_url' => request('redirect_url'),
        ]);
    }

     /**
     * Handle an incoming authentication request.
     *
     * @param  \App\Http\Requests\Auth\LoginRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(LoginRequest $request)
    {  
        $request->authenticate();

        $user = auth()->user();

        // if ($user->type == 1) {
        //     Auth::guard('web')->logout();

        //     $email = $user->email;

        //     $code = generateCode(6);
            
        //     $res = sendEmail($code, $email);
            
        //     if (!$res) {
        //         $data = array("status"=> 0);
        //         return redirect()->back()->with('message', 'もう一度メールアドレスを入力してください！')->with('title', '入力エラー')->with('type', 'dialog')->with('data', $data);
        //     }

        //     Verify::where('to', $email)->update(array('status'=>1));
        //     $data = array("to"=>$email, 'code'=>$code);
        //     Verify::create($data);

        //     $data = array("status"=> 1);

        //     $hide_cat_bar = 1;

        //     return inertia('Auth/AdminEmailVerify', compact('hide_cat_bar', 'email'));
        // }
        $request->session()->regenerate();

        if ($request->redirect_url) {
            return redirect($request->redirect_url);
        }
        return redirect()->intended(RouteServiceProvider::HOME);
    }

    public function verify_email(Request $request) {
        $code = $request->code;
        $email = $request->email;
        $count = Verify::where('to', $email)->where('code', $code)->where('status', 0)->count();
        if ($count) {
            Verify::where('to', $email)->where('code', $code)->where('status', 0)->update(array('status'=>2));
            
            $user = User::where('email', $email)->first();
            Auth::login($user);

            $request->session()->regenerate();

            return redirect()->intended(RouteServiceProvider::HOME);
        } else {
            return redirect()->route('login');
        }
    }

    /**
     * Destroy an authenticated session.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
