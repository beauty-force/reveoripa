<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Str;
use App\Models\Line_account;
use Illuminate\Support\Facades\Log;

class LineAccountController extends Controller
{
    public function disconnect(Request $request) {
        $user = Auth::user();
        $user->update(['line_id' => null]);
        return redirect()->back()->with('message', 'LINEアカウントの連携を解除しました')->with('type', 'dialog');
    }

    public function confirm(Request $request) {
        return view('line_confirm', ['id' => $request->id, 'liffId' => env('LIFF_ID')]);
    }

    public function confirm_in_site(Request $request) {
        $eventId = $request->id;
        $line_account = Line_account::where('eventId', $eventId)->first();
        if ($line_account) {
            $user = Auth::user();
            $user->line_id = $line_account->lineId;
            sendLineMessage($line_account->lineId, "🎉連携が無事に完了🎉

これで限定クーポンや特別ガチャに挑戦する準備は完璧です！

お・ま・た・せ・し・ま・し・た…

LINE連携者限定MAX80%OFFクーポンをプレゼント🎁✨

=====🔥クーポン概要🔥=====

・特典：コインの購入金額割引
・割引率：🔥MAX80％OFF🔥
・コード：LINE-C-EVE
・ご利用方法：クーポン管理画面にコードを入力し、ご購入ください。

=====💵割引率早見表💵=====

80%off 500PT▶100円
50%off 1,000PT▶500円
30%off 5,000PT▶3,500円
23%off 10,000PT▶7,700円
17%off 30,000PT▶24,900円
15%off 50,000PT▶42,500円
13%off 100,000PT▶87,000円  
10%off 500,000PT▶450,000円

=======================

あとは…

高還元カードや推しカードとの出会いを祈ってガチャるだけ😍💕

👇ガチャはこちらから👇
https://reve-oripa.jp/?openExternalBrowser=1");
            $user->save();
            $line_account->delete();
        }
        return redirect()->route('profile.edit');
    }

    public function friend_request(Request $request) {
        $data = $request->all();
        Log::info('LINE Webhook received:', $data);

        if (isset($data['events'][0]['type']) && $data['events'][0]['type'] === 'follow') {
            $lineUserId = $data['events'][0]['source']['userId'];
            $eventId = $data['events'][0]['webhookEventId'];
            Line_account::create([
                'lineId' => $lineUserId,
                'eventId' => $eventId
            ]);
            sendLineMessage($lineUserId, "🎉LINEの友だち追加🎉🎉 
🎉ありがとうございます🎉 

LINEで新発売オリパの優先告知やお得なクーポンをお届けします✨ 

さ・ら・に… 

イブガチャとLINEを連携すれば…爆裂チャンスが💥😳💥 

==連携済ユーザー限定特典== 
✅超割引クーポン 
　…MAX80% 割引 
✅超高還元オリパガチャに挑戦 
　…LINE連携者限定の超還元ガチャなど‼ 
===================== 

👇今すぐLINE連携を完了して👇 
👇特典をゲットしよう👇👇👇 

🔗 LINE連携を完了する 
https://reve-oripa.jp/line/confirm?id=$eventId&openExternalBrowser=1
(20秒で即連携可能🔥)
            ");

            return response()->json(['status' => 'success']);
        }

        return response()->json(['status' => 'ignored']);
    }
}
