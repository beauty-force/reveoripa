<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Str;
use DB;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Inertia\Response
     */
    public function edit(Request $request)
    {
        $user = auth()->user();
        if ($user->invite_code == null) {
            $user->invite_code = Str::random(8);
            $user->save();
        }

        $invitations = DB::table('invitations')
            ->leftjoin('users', 'users.id', '=', 'invitations.user_id')
            ->leftjoin('profiles', 'profiles.user_id', '=', 'invitations.user_id')
            ->select('invitations.id', 'users.name', 'invitations.status')
            ->where('invitations.inviter', $user->id)
            ->get();

        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'invitations' => $invitations,
            'status' => session('status'),
            'hide_cat_bar' => 1,
            // 'invite_url' => 'https://reve-oripa.jp/register?invitation_code='.$user->invite_code,
            // 'invite_bonus' => getOption('invite_bonus'),
            // 'invited_bonus' => getOption('invited_bonus'),
            // 'line_link_url' => 'https://line.me/R/ti/p/@775loedl',
//             'line_invite_text' => "【🎉レヴオリパに招待されました！🎉】

// 友達からレヴオリパへの特別な招待が届いています！新規登録時に以下の招待コードを入力すると、紹介した友達とあなた、2人ともにボーナスポイントが付与されます！🌟

// 🔑 招待コード: 【$user->invite_code"."】 
// 🔗 登録はこちらから: https://reve-oripa.jp/register?invitation_code=$user->invite_code

// この機会にレヴオリパで楽しい時間を過ごしましょう！🎁",
//             'twitter_invite_text' => "【業界No.1 水準 レヴオリパ✨】

// 友達紹介キャンペーン中⚡️
// 新規登録時に以下の招待コードを入力すると、紹介した友達とあなた、2人ともにボーナスポイントが付与されます！🌟

// 🔑 招待コード: 【$user->invite_code"."】 
// 🔗 登録はこちらから
// （https://reve-oripa.jp/register?invitation_code=$user->invite_code)"
        ]);
    }

    /**
     * Update the user's profile information.
     *
     * @param  \App\Http\Requests\ProfileUpdateRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(ProfileUpdateRequest $request)
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($request->avatar) {
            if (!file_exists('images/avatars')) {
                mkdir('images/avatars', 0777, true);
            }
            if ($user->avatar && file_exists(public_path('images/avatars/'.$user->avatar))) unlink(public_path('images/avatars/'.$user->avatar));
            $url = saveImage('images/avatars', $request->file('avatar'), false);
            $user->update(['avatar' => $url]);
        }
        return Redirect::route('profile.edit')->with('data', ['user' => $user]);
    }

    /**
     * Delete the user's account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'password' => ['required', 'current-password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->update(['status' => 2]);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
