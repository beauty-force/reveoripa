<?php
use Twilio\Rest\Client;
use App\Models\Category;
use App\Models\Option;
use App\Models\User;
use App\Models\Rank;
use App\Models\Gacha_video;

use App\Http\Resources\CategoryListResource;
use App\Http\Controllers\PointHistoryController;

use Intervention\Image\Facades\Image as ImageResize;

if ( ! function_exists('isPhoneNumber')){
    function isPhoneNumber($string){
        $isPhoneNum = false;
        //eliminate every char except 0-9
        $justNums = preg_replace("/[^0-9]/", '', $string);

        //eliminate leading 1 if its there
        // if (strlen($justNums) == 11) $justNums = preg_replace("/^1/", '',$justNums);

        //if we have 10 digits left, it's probably valid.
        if (strlen($justNums) == 11) $isPhoneNum = true;
        if (strlen($justNums)!=strlen($string)) $isPhoneNum = false;
        return $isPhoneNum;
    }
}

if ( ! function_exists('isValidEmail')){
    function isValidEmail($email){ 
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if ( ! function_exists('generateCode')){
    function generateCode($length)
    {
        $max = pow(10, $length) - 1;
        $rand = random_int(0, $max);
        $code = sprintf('%0'. $length. 'd', $rand);

        return $code;
    }
}

if ( ! function_exists('sendCode')){
    function sendCode($code, $to) 
    {
        $client = new Client(env('TWILIO_SID'), env('TWILIO_TOKEN'));
        $channel = 'sms';
        try {
            if($channel == 'sms') {
                $client->messages->create(
                    $to,
                    [
                        'from' => env( 'TWILIO_FROM' ),
                        'body' => 'reve-oripaの認証コードは '. $code . ' です。',
                        // 'body' => ' '.$code.' です。',
                    ]);
                return true;
            } else if($channel == 'voice'){
                $client->calls->create(
                    $to,
                    env( 'TWILIO_FROM_VOICE' ), // from
                    [
                        "twiml" => "<Response><Say language='ja-Jp'>あなたの認証コードは".join(',',str_split($code))."です。</Say></Response>"
                    ]);
                return true;
            } 
        }
        catch(Exception $e) {
            return false;
        }

        return false;
        
    }
}

if ( ! function_exists('sendEmail')){
    function sendEmail($code, $email) 
    {
        $content = 'Reve-oripaをご利用頂きありがとうございます。
以下の認証コードを認証コード入力画面に入力してください。

認証コード：'.$code.'

※このメールは送信専用のため返信できません。
reve-oripa';
        Mail::raw($content, function ($message) use ($email)
        {
            $message->to($email)
                ->subject('メールアドレス認証のお願い');
            $message->from(env('MAIL_FROM_ADDRESS'), 'reve-oripa');
        });
        return true;
    }
}

if ( ! function_exists('getCategoryTitle')){
    function getCategoryTitle($cat_id)
    {
        return Category::where('id', $cat_id)->first()?->title;
    }
}

if ( ! function_exists('getCategories')){
    function getCategories() 
    {
        $categories = Category::orderby('order', 'asc')->select('id', 'title', 'order')->get();
        // $categories = CategoryListResource::collection($categories);
        return $categories;
    }
}

if ( ! function_exists('getPointImageUrl')){
    function getPointImageUrl($url)
    {
        return '/images/point/' . $url;
    }
}


if ( ! function_exists('getGachaImageUrl')){
    function getGachaImageUrl($url)
    {
        return '/images/gacha/' . $url;
    }
}

if ( ! function_exists('getGachaThumbnailUrl')){
    function getGachaThumbnailUrl($url)
    {
        return '/images/gacha/thumbnail/' . $url;
    }
}

if ( ! function_exists('getProductImageUrl')){
    function getProductImageUrl($url)
    {
        return '/images/products/' . $url;
    }
}

if ( ! function_exists('getRankImageUrl')){
    function getRankImageUrl($url)
    {
        if (is_numeric($url)) {
            $url = Rank::where('rank', $url)->first()?->badge;
        }
        if (!$url) return null;
        return '/images/ranks/' . $url;
    }
}

if ( ! function_exists('combineRoute')){
    function combineRoute($org, $cat_id)
    {
        return $org.'?cat_id=' . $cat_id;
    }
}

if ( ! function_exists('saveImage')){
    function saveImage($path_str, $file, $need_thumbnail=false)
    {
        
        // $file = $request->file('image');
        $extension = $file->getClientOriginalExtension();

        $path = public_path($path_str);  //'images/dp'
        if (!file_exists($path)) {
            mkdir($path, 0777, true);
        }

        if ($need_thumbnail) {
            $path_thumbnail = public_path($path_str.'/thumbnail');
            if (!file_exists($path_thumbnail)) {
                mkdir($path_thumbnail, 0777, true);
            }
        }

        $filenametostore = uniqid();
        $name = $filenametostore . '.' . $extension;

        if ($need_thumbnail) {
            $img = ImageResize::make($file->path());
            $img->resize(200, 200, function ($constraint) {
                $constraint->aspectRatio();
            })->save($path_thumbnail.'/'.$name);
        }
        $file->move($path, $name);

        return $name;
    }
}

if ( ! function_exists('getOption')){
    function getOption($name)
    {
        if ($name == 'is_busy') {
            $options = Option::where('name', $name)->where('updated_at', '>', now()->subSeconds(10))->get();
            if (count($options)) {
                return $options[0]->value;
            }
            else {
                setOption($name, 0);
                return 0;
            }
        }
        $options = Option::where('name', $name)->get();
        if (count($options)) {
            return $options[0]->value;
        } else {
            return "";
        }
    }
}

if ( ! function_exists('setOption')){
    function setOption($name, $value)
    {
        $options = Option::updateOrCreate(['name'=>$name], ['value'=>$value]);
    }
}

if ( ! function_exists('getVideoUrl')){
    function getVideoUrl($url)
    {
        return '/videos/' . $url;
    }
}

if ( ! function_exists('getVideo')){
    function getVideo($gacha_id, $gacha_point, $point)
    {
        $videos = Gacha_video::where('gacha_id', $gacha_id)->orderBy('point')->get();

        if (!count($videos)) return "default.mp4";

        $index = 0;
        for ( ; $index < count($videos); $index ++) {
            if ($videos[$index]->point > $point) break;
        }
        
        if ($index == 0) return "default.mp4";
        $index --;
        $from = $index;
        while ($from > 0 && $videos[$from-1]->point == $videos[$index]->point) {
            $from --;
        }

        $index = rand($from, $index);

        if ($videos[$index]->file) return $videos[$index]->file;
        
        $video = Gacha_video::where('gacha_id', 0)->where('level', $videos[$index]->level)->first();
        if ($video) return $video->file;
        return "default.mp4";
    }
}

if ( ! function_exists('getProductStatusTxt')){
    function getProductStatusTxt()
    {
        $txts = [];
        $txts['S+'] = ['short'=>'超美品', 'long'=>"実質完璧な状態。カードの四つ角にも全く傷がなく、どんなシミや汚れもない状態。"];
        $txts['S'] = ['short'=>'超美品', 'long'=>"非常に優れた状態。ごくわずかな傷や汚れがないもので繊細な傷がひとつ見受けられる程度の状態。"];
        $txts['A+'] = ['short'=>'美品', 'long'=>"完璧な優れた状態のカードに見えるが、精査するとわずかなシミや汚れがあるような状態。"];
        $txts['A'] = ['short'=>'美品', 'long'=>"細かく精査すると表面にほんのわずかな傷や汚れがあるが、極めて状態の良いもの。"];
        $txts['B+'] = ['short'=>'良品', 'long'=>"視認できる表面に印刷上の欠陥はあるが、全体的に見栄えの損なわない状態。"];
        $txts['B'] = ['short'=>'良品', 'long'=>"大きな傷の無いもの、角や端に極めて繊細な傷があり、印刷上の欠陥が視認しやすいもの。"];
        $txts['-'] = ['short'=>'並品', 'long'=>"カードに軽い傷や擦り傷がついているがしっかりと視認しないと気が付かないレベルの状態。"];
        $txts['シュリンク一部破れあり'] = ['short'=>'並品', 'long'=>"カードの四隅は明らかに丸みがあり、ひどくはないが擦り傷がある状態。"];
        $txts['シュリンク一部汚れあり'] = ['short'=>'訳あり品', 'long'=>"角は丸みがあり、表面に分かりやすい傷、汚れやシミがまたは削れがある状態。"];
        $txts['箱へこみあり'] = ['short'=>'訳あり品', 'long'=>"カード全体の見栄えが悪く、大きな傷や欠陥がある状態。"];
        return $txts;
    }
}

if ( ! function_exists('computeUserRank')){
    function computeUserRank($user)
    {
        $now = date('Y-m');
        if ($user->month == $now) return $user;
        $rank = Rank::where('rank', $user->current_rank)->first();

        if ($rank && $user->consume_point < $rank->keep_limit && $user->current_rank > 1) {
            $user->current_rank --;
        }
        $user->month = $now;
        $user->consume_point = 0;
        $user->save();

        return $user;
    }
}

if ( ! function_exists('sendLineMessage')){
    function sendLineMessage($userId, $message)
    {
        $accessToken = env('LINE_CHANNEL_ACCESS_TOKEN');

        $response = Http::withHeaders([
            'Authorization' => "Bearer $accessToken",
            'Content-Type' => 'application/json',
        ])->post('https://api.line.me/v2/bot/message/push', [
            'to' => $userId,
            'messages' => [
                [
                    'type' => 'text',
                    'text' => $message
                ]
            ]
        ]);

        return $response->json();
    }
}

if ( ! function_exists('getProductBadgeUrl')){
    function getProductBadgeUrl($rare, $product_id)
    {
        if ($rare == 'PSA' && $product_id > 1556) return '/images/psa10.png';
        return null;
    }
}