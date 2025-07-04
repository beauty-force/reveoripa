<?php

namespace App\Http\Controllers\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Http\Requests\RegisterRequest;


use Str;

use App\Models\User;
use App\Models\Verify;
use App\Models\Invitation;

use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\PointHistoryController;

class RegisterController extends Controller
{
    public function create(Request $request) {
        if(Auth::check()) {
            if (auth()->user()->getType() == 'admin') {
                return redirect()->route('admin');
            }else{
                return redirect()->route('user');
            }
        }
        $invitation_code = $request->invitation_code;
        $invited_bonus = getOption('invited_bonus');
        if ($invited_bonus == '') $invited_bonus = 0;
        else $invited_bonus = intval($invited_bonus);
        $hide_cat_bar = 1;
        return inertia('Auth/RegisterEmail', compact('hide_cat_bar', 'invitation_code', 'invited_bonus'));
    }

    public function send(Request $request) {
        $phone = $request->phone; $data = array();
        $phone = trim($phone);
        
        
        if (!isPhoneNumber($phone)) {
            return redirect()->back()->with('message', '11桁の電話番号を入力してください！')->with('title', '入力エラー')->with('type', 'dialog')->with('data', $data);
        }
        $count = User::where('phone', $phone)->count();
        if ($count>0) {
            return redirect()->back()->with('message', 'すでに登録された電話番号です！')->with('title', 'エラー')->with('type', 'dialog')->with('data', $data);
        }
        
        $code = generateCode(4);
        
        $res = sendCode($code, "+81". $phone);
        if (!$res) {
            $data = array("status"=> 0);
            return redirect()->back()->with('message', '電話番号を再度入力してください！'.$res)->with('title', '入力エラー')->with('type', 'dialog')->with('data', $data);
        }

        Verify::where('to', $phone)->update(array('status'=>1));
        $data = array("to"=>$phone, 'code'=>$code);
        Verify::create($data);

        $data = array("status"=> 1, "phone"=> $phone);
        return redirect()->back()->with('data', $data);
    }

    public function verify(Request $request) {
        $code = $request->code;
        $phone = $request->phone;
        $count = Verify::where('to', $phone)->where('code', $code)->where('status', 0)->count();
        if ($count) {
            Verify::where('to', $phone)->where('code', $code)->where('status', 0)->update(array('status'=>2));
            $data = array("status"=> 1);
            return redirect()->back()->with('data', $data);
        } else {
            $data = array();
            return redirect()->back()->with('message', '再度SMS認証をお願い致します！')->with('title', 'エラー')->with('type', 'dialog')->with('data', $data);
        }
    }

    public function send_email(Request $request) {
        $email = $request->email; $data = array();
        $email = trim($email);
        
        $request->validate([
            'email' => 'required|string|email:rfc,dns|max:255|unique:users'
        ]);
        $count = User::where('email', $email)->count();
        if ($count>0) {
            return redirect()->back()->with('message', 'このメールはすでに登録されています！')->with('title', 'エラー')->with('type', 'dialog')->with('data', $data);
        }

        $code = generateCode(4);
        // $code = "1111";
        
        $res = sendEmail($code, $email);
        
        if (!$res) {
            $data = array("status"=> 0);
            return redirect()->back()->with('message', 'もう一度メールアドレスを入力してください！')->with('title', '入力エラー')->with('type', 'dialog')->with('data', $data);
        }

        Verify::where('to', $email)->update(array('status'=>1));
        $data = array("to"=>$email, 'code'=>$code);
        Verify::create($data);

        $data = array("status"=> 1);
        return redirect()->back()->with('data', $data);
    } 

    public function verify_email(Request $request) {
        $code = $request->code;
        $email = $request->email;
        $count = Verify::where('to', $email)->where('code', $code)->where('status', 0)->count();
        if ($count) {
            Verify::where('to', $email)->where('code', $code)->where('status', 0)->update(array('status'=>2));
            $data = array("status"=> 1);
            return redirect()->back()->with('data', $data);
        } else {
            $data = array();
            return redirect()->back()->with('message', '再度SMS認証をお願い致します！')->with('title', 'エラー')->with('type', 'dialog')->with('data', $data);
        }
    }

    public function register(Request $request) { 
        $request->validate([
            'password' => 'required|min:6|max:20',
            'password_confirmation' => 'required|same:password',
            'phone' => 'required|string',
        ]);

        $phone = $request->phone;
        $email = $request->email;

        $phone = trim($phone); $data = array();
        
        if (!isPhoneNumber($phone)) {
            return redirect()->back()->with('message', '11桁の電話番号を入力してください！')->with('title', '入力エラー')->with('type', 'dialog')->with('data', $data);
        }
        $count = User::where('phone', $phone)->count();
        if ($count>0) {
            return redirect()->back()->with('message', 'すでに登録された電話番号です！')->with('title', 'エラー')->with('type', 'dialog')->with('data', $data);
        }
        $count = User::where('email', $email)->count();
        if ($count>0) {
            return redirect()->back()->with('message', 'すでに登録されているメールアドレスです！')->with('title', 'エラー')->with('type', 'dialog')->with('data', $data);
        }

        $friend = null;
        if (isset($request->invite_code)) {
            $invite_code = trim($request->invite_code);
            $friend = User::where('invite_code', $invite_code)->first();
            if ($friend == null) {
                return redirect()->back()->with('message', '有効な招待コードを入力してください。')->with('title', 'エラー')->with('type', 'dialog');
            }
        }

        $email_verified_at = Verify::where('to', $email)->where('status', 2)->first()?->updated_at;
        $user = User::create([
            'name' => "はじめてのコレクター",
            'email' => $email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'point' => 0,
            'email_verified_at' => $email_verified_at
        ]);
        if ($friend) {
            $invitation = Invitation::create([
                'user_id' => $user->id,
                'inviter' => $friend->id
            ]);
            $invite_bonus = getOption('invite_bonus');
            if ($invite_bonus == '') $invite_bonus = '0';
            $invite_bonus = intval($invite_bonus);

            $invitations = Invitation::where('inviter', $friend->id)->count();
            
            if ($invitations <= 30 && $invite_bonus > 0) {
                (new PointHistoryController)->create($friend->id, $friend->point, $invite_bonus, 'invite', $invitation->id);
                $friend->point += $invite_bonus;
                $friend->save();
            // }

            // $invited_bonus = getOption('invited_bonus');
            // if ($invited_bonus == '') $invited_bonus = '0';
            // $invited_bonus = intval($invited_bonus);
            
            // if ($invited_bonus > 0) {
                (new PointHistoryController)->create($user->id, $user->point, $invite_bonus, 'invited', $invitation->id);
                $user->point += $invite_bonus;
                $user->save();
            }   
        }
        event(new Registered($user));

        Auth::login($user);

        // $user->sendEmailVerificationNotification();
        
        return redirect()->route('register.complete');
    }

    public function complete() {
        $hide_cat_bar = 1;
        return Inertia::render('Auth/RegisterComplete', compact('hide_cat_bar'));
    }
}
