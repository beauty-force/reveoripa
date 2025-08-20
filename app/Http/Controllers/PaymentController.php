<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

use App\Models\Point;
use App\Models\Coupon;
use App\Models\Coupon_record;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\User;
use App\Models\Rank;
use App\Models\Plan;
use App\Models\Invitation;
use App\Models\UserSubscription;
use App\Models\Bank_payment;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PointHistoryController;
use Illuminate\Support\Facades\Log;

use \Exception;
use Str;
use Auth;
use Amazon;

class PaymentController extends Controller
{
    protected $client;

    public $config = [
        'testOrLive' => 'live',
        'is3DSecure' => '1',
        'fincode_public_key' => '',
        'fincode_secret_key' => '',
        'apiDomain' => '',
    ];
    public $amazonpay_config = [
        'public_key_id' => '',
        'private_key'   => '',
        'region'        => '',
        'sandbox'       => '',
        'algorithm' => ''
    ];

    protected function set_config() {
        $this->config['testOrLive'] = getOption('testOrLive');
        $this->config['is3DSecure'] = getOption('is3DSecure');

        if ($this->config['testOrLive'] =="test") {
            $this->config['fincode_public_key'] = env('FINCODE_TEST_API_KEY');
            $this->config['fincode_secret_key'] = env('FINCODE_TEST_SECRET_KEY');
            $this->config['apiDomain'] = "https://api.test.fincode.jp";
        } else {
            $this->config['fincode_public_key'] = env('FINCODE_LIVE_API_KEY');
            $this->config['fincode_secret_key'] = env('FINCODE_LIVE_SECRET_KEY');
            $this->config['apiDomain'] = "https://api.fincode.jp";
        }
    }

    protected function set_amazonpay_config() {
        $sandbox = env('AMAZON_PAY_SANDBOX', true);
        $public_key_id = $sandbox ? env('AMAZON_PAY_PUBLIC_KEY_ID_SANDBOX') : env('AMAZON_PAY_PUBLIC_KEY_ID');
        
        $this->amazonpay_config['public_key_id'] = $public_key_id;
        $this->amazonpay_config['private_key']   = base_path($sandbox ? env('AMAZON_PAY_PRIVATE_KEY_PATH_SANDBOX') : env('AMAZON_PAY_PRIVATE_KEY_PATH'));
        $this->amazonpay_config['region']        = env('AMAZON_PAY_REGION');
        $this->amazonpay_config['sandbox']       = $sandbox;
        $this->amazonpay_config['algorithm'] = 'AMZN-PAY-RSASSA-PSS-V2';
    }

    public function do_request($apiPath, $method, $requestParams) {
        $res = [
            'status' => 1,
            'response' => '',
            'httpcode' => '0',
            'error' => '',
        ];

        try{
            $session = curl_init();
            curl_setopt($session, CURLOPT_URL, $this->config['apiDomain'].$apiPath);
            curl_setopt($session, CURLOPT_CUSTOMREQUEST, $method);

            $headers = array(
                "Authorization: Bearer " . $this->config['fincode_secret_key'],
                "Content-Type: application/json"
                );
            curl_setopt($session, CURLOPT_HTTPHEADER, $headers);

            if ($requestParams) {
                $requestParamsJson = json_encode($requestParams);
                curl_setopt($session, CURLOPT_POSTFIELDS, $requestParamsJson);
            }
            

            curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
            $response = curl_exec($session);
            $httpcode = curl_getinfo($session, CURLINFO_HTTP_CODE);
            
            curl_close($session);
            $res['response'] = $response;
            $res['httpcode'] = $httpcode;

        } catch(Exception $e) {
            $text = "処理中に問題が発生しました！ " ;
            $res['status'] = 0;
            $res['error'] = $text;
        }

        return $res;
    }

    public function get_discount_rate($code, $point_id) {
        $coupon = Coupon::where('code', $code)->where('type', 'DISCOUNT')->first();
        if ($coupon) {
            if ($coupon->expiration <= date('Y-m-d H:i:s')) {
                return -3;
            }
            $count = Coupon_record::where(['coupon_id' => $coupon->id, 'user_id' => auth()->user()->id])->count();
            if ($count >= $coupon->user_limit) {
                return -2;
            }
            $records = Coupon_record::where(['coupon_id' => $coupon->id])->count();
            if ($records >= $coupon->count) {
                return -1;
            }
            $discount_rate = $coupon?->discount_rate->toArray();
            if ($discount_rate && count($discount_rate) > 0 && $coupon->expiration > now()) {
                foreach($discount_rate as $rate) if ($rate['point_id'] == $point_id) return $rate['rate'];
            }
        }
        return 0;
    }

    public function purchase(Request $request) {
        $code = isset($request->code) ? $request->code : '';
        $current_rate = $this->get_discount_rate($code, $request->id);
        
        if ($current_rate == -3) {
            return redirect()->back()->with('message', '有効期間を超えました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        if ($current_rate == -2) {
            return redirect()->back()->with('message', 'すでにこのコードを制限回数分使用しました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        if ($current_rate == -1) {
            return redirect()->back()->with('message', '使用可能な人数を超えました。')->with('title', '取得エラー')->with('type', 'dialog');
        }

        $this->set_config();
        $hide_cat_bar = 1;

        $point = Point::where('id', $request->id)->first();
        if (!$point) {
            return redirect()->route('user.point');
        }

        $user = auth()->user();
        
        $is_admin = 0;
        if ($user) {
            if ( $user->type==1 ) {
                $is_admin = 1;
            }
        }
        
        $amount = $point->amount - intval($point->amount * $current_rate / 100);

        // if ($is_admin != 1 && $this->config['testOrLive'] != 'live') {
        //     return redirect()->route('user.point');
        // }
        
        $no_applepay_setting = "Applepayの設定が完了していません。";
        $no_applepay_support = "このデバイスはApplepayをサポートしていません。";

        $rank = Rank::where('rank', $user->current_rank)->first();
        
        if ($user->id <= 2) $supported_pay_type = ['Card', 'Virtualaccount'];
        else $supported_pay_type = ['Card', 'Virtualaccount'];
        return inertia('User/Point/Purchase', compact('point', 'is_admin', 'amount', 'hide_cat_bar', 'supported_pay_type', 'rank', 'code', 'no_applepay_setting', 'no_applepay_support'));

    }

    public function card_payment(Request $request)
    {
        $code = isset($request->code) ? $request->code : '';
        $current_rate = $this->get_discount_rate($code, $request->id);
        
        if ($current_rate == -3) {
            return redirect()->back()->with('message', '有効期間を超えました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        if ($current_rate == -2) {
            return redirect()->back()->with('message', 'すでにこのコードを制限回数分使用しました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        if ($current_rate == -1) {
            return redirect()->back()->with('message', '使用可能な人数を超えました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        
        $this->set_config();
        
        $point = Point::find($request->id);
        if (!$point) {
            return redirect()->route('user.point');
        }
        
        $user = auth()->user();
        
        $is_admin = 0;
        if ($user) {
            if ( $user->type==1 ) {
                $is_admin = 1;
            }
        }
        
        $amount = $point->amount - intval($point->amount * $current_rate / 100);

        // if ($is_admin != 1 && $this->config['testOrLive'] != 'live') {
        //     return redirect()->route('user.point');
        // }
        
        $testOrLive = $this->config['testOrLive'];

        $registeredCards = [];
        if ($user->customer_id) {
            $apiPath = "/v1/customers/$user->customer_id/cards";
            $res = $this->do_request($apiPath, 'GET', []);
            if ($res['httpcode'] == 200) {
                $res = json_decode($res['response']);
                $registeredCards = $res->list;
            }
        }

        return inertia('User/Payment/CardPayment', [
            'registeredCards' => $registeredCards,
            'hide_cat_bar' => 1,
            'point' => $point,
            'code' => $code,
        ]);
    }

    public function card_payment_post(Request $request) {
        $code = isset($request->code) ? $request->code : '';
        $current_rate = $this->get_discount_rate($code, $request->id);
        $coupon = Coupon::where('code', $code)->first();
        
        if ($current_rate == -3) {
            return redirect()->back()->with('message', '有効期間を超えました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        if ($current_rate == -2) {
            return redirect()->back()->with('message', 'すでにこのコードを制限回数分使用しました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        if ($current_rate == -1) {
            return redirect()->back()->with('message', '使用可能な人数を超えました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        
        $this->set_config();
        
        $point = Point::find($request->id);
        if (!$point) {
            return redirect()->route('user.point');
        }
        
        $user = auth()->user();
        
        $is_admin = 0;
        
        $amount = $point->amount - intval($point->amount * $current_rate / 100);

        $apiPath = "/v1/payments";
        $method = 'POST';
        $requestParams = array(
            "pay_type" => "Card",
            "job_code" => "CAPTURE",
            "amount" => strval($amount),
            "client_field_1" => str($user->id),
            "client_field_2" => str($point->id),
            "client_field_3" => str($coupon->id ?? 0) 
        );        
        if ($this->config['is3DSecure']=="1") {
            $requestParams['tds_type'] = "2";  //   3DS2.0を利用
            $requestParams['td_tenant_name'] = "reve-oripa";  //   3Dセキュア表示店舗名
            // $requestParams['tds2_type'] = "3";  //   3DS2.0の認証なしでオーソリを実施し、決済処理を行う。
            $requestParams['tds2_type'] = "2";  //   エラーを返し、決済処理を行わない。
        }

        $res = $this->do_request($apiPath, $method, $requestParams);
        if ($res['httpcode']!='200') {
            $text = "決済登録エラー！\n";
            $text .= $this->getErrorText($res) ;
            $hide_back_btn = 1; $hide_cat_bar = 1;
            return inertia('NoProduct', compact('text', 'hide_back_btn', 'hide_cat_bar'));
        }
        $json_data = json_decode($res['response']);
        $order_id = $json_data->id;
        $access_id = $json_data->access_id;

        $apiPath = "/v1/payments/$order_id";
        $method = 'PUT';
        $requestParams = [
            "pay_type" => "Card",
            "access_id" => $access_id,
            "customer_id" => $user->customer_id,
            "card_id" => $request->card_id,
            "method" => "1",
        ];
        if ($this->config['is3DSecure']=="1") {
            $requestParams['tds2_ret_url'] = route('tds2_ret_url');  
        }
        $res = $this->do_request($apiPath, $method, $requestParams);
        if ($res['httpcode']!='200') {
            $text = "決済登録エラー！\n";
            $text .= $this->getErrorText($res) ;
            $hide_back_btn = 1; $hide_cat_bar = 1;
            return inertia('NoProduct', compact('text', 'hide_back_btn', 'hide_cat_bar'));
        }
        $json_data = json_decode($res['response']);
        if ($json_data->status=="CAPTURED") {
            $res['status'] = $json_data->status;
        }
        else if ($json_data->status=="AUTHENTICATED") {
            $res['acs_url'] = $json_data->acs_url; 
            $res['status'] = $json_data->status;
        }
        return json_encode($res);
    }

    public function tds2_ret_url(Request $request) {
        return $this->handle_tds2_ret_url($request->MD, $request->event, $request->param, "Card");
    }

    public function tds2_ret_url_googlepay(Request $request) {
        return $this->handle_tds2_ret_url($request->MD, $request->event, $request->param, "Googlepay");
    }

    public function handle_tds2_ret_url($access_id, $event, $param, $pay_type) {
        $text = "" ;
        $this->set_config();
        
        if ($event!="AuthResultReady") {
            $apiPath = "/v1/secure2/$access_id";
            $method = 'PUT';
            $requestParams = [
                'pay_type' => $pay_type,
                'param' => $param
            ];
            
            $ans = $this->do_request($apiPath, $method, $requestParams);
            if ($ans['httpcode']!='200') {
                $text = $this->getErrorText($ans);
            } else {
                $json_data = json_decode($ans['response']);
                $text = "" ;
                switch ($json_data->tds2_trans_result) {
                    case 'Y':
                        $ans = $this->payment_after_auth($access_id, $pay_type);
                        $text .= "\n" . $ans['error'];
                        break;
                    case 'C':
                        header('Location: '. $json_data->challenge_url); 
                        die();
                        exit();
                        break;
                    case 'A':
                        $ans = $this->payment_after_auth($access_id, $pay_type);
                        $text = "3Dセキュア利用ポリシー設定が認証必須の設定の場合はエラーです。(A)";
                        $text .= "\n" . $ans['error'];
                        break;
                    default:
                        $text = "認証失敗しました！ ($json_data->tds2_trans_result)";
                }
            }
        } else {
            
            $apiPath = "/v1/secure2/$access_id?pay_type=$pay_type";
            $method = 'GET';
            $ans = $this->do_request($apiPath, $method, []);
            if ($ans['httpcode']!='200') {
                $text .= $this->getErrorText($ans);
            } else {
                $json_data = json_decode($ans['response']);
                if ($json_data->tds2_trans_result!='Y') {
                    $text = "チャレンジ認証失敗しました！($json_data->tds2_trans_result)" ;
                } else {
                    $res = $this->payment_after_auth($access_id, $pay_type);
                    $text .= "\n" . $res['error'];
                }
            }
        }

        $hide_back_btn = 1;
        $hide_cat_bar = 1;
        
        return inertia('NoProduct', compact('text', 'hide_back_btn', 'hide_cat_bar'));
    }

    public function payment_after_auth($access_id, $pay_type) {
        // 認証後決済
        $res = [
            'status' => 0,
            'error' => '',
        ];
        $payments = Payment::where('access_id', $access_id)->where('status', 0)->get();
        if (count($payments)) {
            $payment = $payments[0];
            $user = User::find($payment->user_id);
            $order_id = $payment->order_id;

            $apiPath = "/v1/payments/$order_id/secure";
            $method = 'PUT';
            $requestParams = ['pay_type'=>$pay_type, 'access_id'=>$access_id];
            $ans = $this->do_request($apiPath, $method, $requestParams);
            if ($ans['httpcode']!='200') {
                $res['error'] = "認証後決済エラー！\n";
                $res['error'] .= $this->getErrorText($ans);
                return $res;
            } else {
                $json_data = json_decode($ans['response']);
                
                if (isset($json_data->status)) {
                    if ($json_data->status == 'CAPTURED') {
                        $redirect_uri = ($user?->type==1)? 'test.purchase_success': 'purchase_success';
                        header('Location: '. route($redirect_uri)); 
                        die();
                        exit();
                    }
                } else {
                    $res['error'] = "認証後決済エラー！\n";
                    $res['error'] .= '状態が存在しません。';
                }
            }
        } else {
            $res = [
                'status' => 0,
                'error' => 'データベースに取引が存在しません。',
            ];
        }
        return $res;
    }

    public function card_register(Request $request) {
        $user = auth()->user();
        if (!$user) return redirect('main');
        
        $this->set_config();

        $apiPath = "/v1/card_sessions";
        $method = 'POST';

        $uri = $request->type == 'purchase' ? 'purchase.card_register_callback' : 'subscription.card_register_callback';
        $requestParams = [
            'success_url' => route($uri),
            'cancel_url' => route($uri)
        ];
        if ($user->customer_id) {
            $requestParams['customer_id'] = $user->customer_id;
        }

        $res = $this->do_request($apiPath, $method, $requestParams);
        if ($res['httpcode'] == 200) {
            $res = json_decode($res['response']);
            
            if (!$user->customer_id) {
                $user->customer_id = $res->customer_id;
                $user->save();
            }
            return [
                'status' => 1,
                'link_url' => $res->link_url
            ];
        }
        $text = "決済登録エラー！\n";
        $text .= $this->getErrorText($res) ;
        return [
            'status' => 0,
            'message' => $text
        ];

    }

    public function card_register_callback() {
        $uri = 'user.point';
        if (auth()->user()?->type == 1) $uri = 'test.'.$uri;
        return redirect()->route($uri);
    }

    public function card_register_callback_subscription() {
        $uri = 'user.subscription.index';
        if (auth()->user()?->type == 1) $uri = 'test.'.$uri;
        return redirect()->route($uri);
    }

    public function deleteCard(Request $request) {
        $user = auth()->user();
        if ($user?->customer_id) {
            $this->set_config();

            $apiPath = "/v1/customers/$user->customer_id/cards/$request->card_id";
            $method = 'DELETE';
            $requestParams = [];

            $res = $this->do_request($apiPath, $method, $requestParams);
            if ($res['httpcode'] == 200) {
                $res = json_decode($res['response']);
                
                return [
                    'status' => 1,
                ];
            }
        }
        return [
            'status' => 0,
        ];
    }

    public function paymentExecute(Request $req) {
        $this->set_config();
        
        $apiPath = "/v1/payments/".$req->order_id;
        $method = 'PUT';

        $requestParams = $req->all();
        unset($requestParams['order_id']);

        if ($req->pay_type == 'Googlepay' && $this->config['is3DSecure']=="1") {
            $requestParams['tds2_ret_url'] = route('tds2_ret_url_googlepay');  
        }
        
        $ans = $this->do_request($apiPath, $method, $requestParams);
        
        if ($ans['httpcode']!='200') {
            $text = "決済実行エラー！\n";
            $text .= $this->getErrorText($ans) ;
            $hide_back_btn = 1; $hide_cat_bar = 1;
            return inertia('NoProduct', compact('text', 'hide_back_btn', 'hide_cat_bar'));
        }
        
        $res['status'] = 1;
        $res['redirect_uri'] = route((auth()->user()->type==1) ? 'test.purchase_success': 'purchase_success');
        
        if ($req->pay_type == 'Googlepay') {
            $json_data = json_decode($ans['response']);
            if ($json_data->status=="CAPTURED") {
                
            }
            else if ($json_data->status=="AUTHENTICATED") {
                $res['redirect_uri'] = $json_data->acs_url; 
            }
            else {
                $res['status'] = 0;
                $res['message'] = "決済実行エラー！\n";
                $res['message'] .= $this->getErrorText($ans) ;
            }
        }

        return $res;
    }

    public function paymentRegister(Request $request) {
        $code = isset($request->code) ? $request->code : '';
        $current_rate = $this->get_discount_rate($code, $request->id);
        
        $coupon = Coupon::where('code', $code)->first();
        if ($current_rate == -3) {
            return redirect()->back()->with('message', '有効期間を超えました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        if ($current_rate == -2) {
            return redirect()->back()->with('message', 'すでにこのコードを制限回数分使用しました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        if ($current_rate == -1) {
            return redirect()->back()->with('message', '使用可能な人数を超えました。')->with('title', '取得エラー')->with('type', 'dialog');
        }
        $this->set_config();
        $hide_cat_bar = 1;

        $point = Point::find($request->id);
        if (!$point) {
            return redirect()->route('user.point');
        }

        $user = auth()->user();
        
        $is_admin = 0;
        if ($user) {
            if ( $user->type==1 ) {
                $is_admin = 1;
            }
        }
        
        $amount = $point->amount - intval($point->amount * $current_rate / 100);
        
        $pay_type = $request->pay_type;

        $profile = Profile::where('user_id', $user->id)->first();

        if ($pay_type == 'Card' || $pay_type == 'Paypay' || $pay_type == 'Konbini' || $pay_type == 'Virtualaccount') {

            $apiPath = "/v1/sessions";
            
            $expirationDate = now()->addDays(3)->format('Y/m/d H:i:s');
            $requestParams = [
                "success_url" => route('fincode_success'),
                "cancel_url" => route('fincode_cancel'),
                "expire" => $expirationDate,
                "receiver_mail" => auth()->user()->email,
                "mail_customer_name" => $profile ? $profile->first_name.$profile->last_name : auth()->user()->name,
                "guide_mail_send_flag" => "0",
                "thanks_mail_send_flag" => "0",
                // "shop_mail_template_id" => "test template id",
                "transaction" => [
                    "pay_type" => [
                        $pay_type
                    ],
                    "amount" => str($amount),
                    // "tax" => "0",
                    "client_field_1" => str($user->id),
                    "client_field_2" => str($point->id),
                    "client_field_3" => str($coupon->id ?? 0)
                ],
                "konbini" => [
                    "payment_term_day" => "10",
                    "konbini_reception_mail_send_flag" => "1"
                ],
                "paypay" => [
                    "job_code" => "CAPTURE",
                ],
                "card" => [
                    "job_code" => "CAPTURE",
                    "tds_type" => $this->config['is3DSecure'] == "1" ? "2" : "0"
                ],
                "virtualaccount" => [
                    "payment_term_day" => "10",
                    "virtualaccount_reception_mail_send_flag" => "1"
                ],
            ];
            $res = $this->do_request($apiPath, 'POST', $requestParams);
            
            if ($res['httpcode'] == 200) {
                $res = json_decode($res['response']);
                
                return [
                    'status' => 1,
                    'link_url' => $res->link_url
                ];
            }
            $text = "決済登録エラー！\n";
            $text .= $this->getErrorText($res) ;
            return [
                'status' => 0,
                'message' => $text
            ];
        }
        else if ($pay_type == 'Applepay' || $pay_type == 'Googlepay') {
            $apiPath = "/v1/payments";
            $method = 'POST';
            $requestParams = array(
                "pay_type" => $pay_type,
                "amount" => strval($amount),
                "job_code" => "CAPTURE",
                "client_field_1" => str($user->id),
                "client_field_2" => str($point->id),
                "client_field_3" => str($coupon->id ?? 0)
            );
            if ($pay_type == 'Googlepay') {
                $requestParams['tds_type'] = "0";
                if ($this->config['is3DSecure']=="1") {
                    $requestParams['tds_type'] = "2";  //   3DS2.0を利用
                    $requestParams['td_tenant_name'] = "reve-oripa";  //   3Dセキュア表示店舗名
                    // $requestParams['tds2_type'] = "3";  //   3DS2.0の認証なしでオーソリを実施し、決済処理を行う。
                    $requestParams['tds2_type'] = "2";  //   エラーを返し、決済処理を行わない。
                }
            }

            $res = $this->do_request($apiPath, $method, $requestParams);

            if ($res['httpcode']!='200') {
                $text = "決済登録エラー！\n";
                $text .= $this->getErrorText($res) ;
                return [
                    'status' => 0,
                    'message' => $text
                ];
            }

            $json_data = json_decode($res['response']);
            $order_id = $json_data->id;
            $access_id = $json_data->access_id;

            $json_data->label = "reve-oripa";
            $json_data->amount = str($json_data->amount);

            if ($pay_type == 'Googlepay') {
                $json_data->google_pay_merchant_id = env('GOOGLE_PAY_MERCHANT_ID');
                $json_data->google_pay_environment = ['test' => 'TEST', 'live' => 'PRODUCTION'][getOption('testOrLive')];
                $json_data->google_pay_merchant_name = env('GOOGLE_PAY_MERCHANT_NAME');
            }
            return [
                'status' => 1,
                'response' => $json_data
            ];
        }
        else if ($pay_type == 'amazonpay') {
            $this->set_amazonpay_config();
            $sandbox = env('AMAZON_PAY_SANDBOX', true);
            // if ($sandbox && $user->id > 2) {
            //     return redirect()->route('amazon_pay_cancel');
            // }
            $merchant_id = env('AMAZON_PAY_MERCHANT_ID');
            $public_key_id = $sandbox ? env('AMAZON_PAY_PUBLIC_KEY_ID_SANDBOX') : env('AMAZON_PAY_PUBLIC_KEY_ID');
        
            $client = new Amazon\Pay\API\Client($this->amazonpay_config);
            $customInfomation = json_encode([
                $user->id,
                $point->id,
                $coupon ? $coupon->id : 0,
            ]);

            $payload = [
                "storeId" => $sandbox ? env('AMAZON_PAY_STORE_ID_SANDBOX') : env('AMAZON_PAY_STORE_ID'),
                "webCheckoutDetails" => [
                    "checkoutResultReturnUrl" => route(auth()->user()->getType() == 'admin' ? 'test.purchase_success': 'purchase_success'),
                    "checkoutCancelUrl" => "",
                    "checkoutMode" => "ProcessOrder"
                ],
                "paymentDetails" => [
                    "paymentIntent" => "AuthorizeWithCapture",
                    "canHandlePendingAuthorization" =>false,
                    "chargeAmount" => [
                        "amount" => str($amount),
                        "currencyCode" => env('AMAZON_PAY_CURRENCY_CODE')
                    ],
                ],
                "merchantMetadata" => [
                    'merchantReferenceId' => env('AMAZON_PAY_MERCHANT_ID'),
                    'merchantStoreName' => 'reve-oripa',
                    'noteToBuyer' => '',
                    'customInformation' => $customInfomation
                ],
                "productType" => 'PayOnly'
            ];
            $signature = $client->generateButtonSignature($payload);
            
            return [
                'status' => 1,
                'response' => [
                    'payload' => $payload,
                    'signature' => $signature,
                    'merchant_id' => $merchant_id,
                    'public_key_id' => $public_key_id,
                ]
            ];
        }
    }

    protected function getErrorText($data) {
        $error_print = "";
        try{
            $json_data = json_decode($data['response']);
            if ($json_data->errors) {
                foreach($json_data->errors as $item) {
                    $error_print .= "\n";
                    $error_print .= "$item->error_code : ";
                    $error_print .= "$item->error_message";
                }
            }
        } catch(Exception $e) {
            
        }
        return $error_print;
    }

    private function add_invited_bonus($user) {
        $invitation = Invitation::where('user_id', $user->id)->first();
        if ($invitation && $invitation->status == 0) {
            $inviter = User::find($invitation->inviter);
            if (!$inviter) return ;
            
            $invited_bonus = getOption('invited_bonus');
            if ($invited_bonus == '') $invited_bonus = '0';
            $invited_bonus = intval($invited_bonus);

            $paid_amount = Payment::where('user_id', $user->id)
                ->where('pay_type', '!=', 'subscription')
                ->where('status', 1)
                ->sum('amount');

            if ($invited_bonus > 0 && $paid_amount >= 5000) {
     
                (new PointHistoryController)->create($user->id, $user->point, $invited_bonus, 'invite_payment', $invitation->id);
                $user->increment('point', $invited_bonus);

                (new PointHistoryController)->create($inviter->id, $inviter->point, $invited_bonus, 'invite_payment', $invitation->id);
                $inviter->increment('point', $invited_bonus);

                $invitation->update(['status' => 1]);
            }
        }
    }

    public function webhook (Request $request) {
        $fincode_signature = env('FINCODE_WEBHOOK_SIGNATURE');
        if ($request->header('fincode-signature') != $fincode_signature) {
            $data = json_encode($request->all());
            return response()->json(['error' => 'Invalid signature'], 401);
        }
        Log::info('Fincode Webhook', $request->all());

        if ($request->subscription_id) {
            $user = User::where('customer_id', $request->customer_id)->first();
            if ($request->status == 'ACTIVE') {
                if (UserSubscription::where('subscription_id', $request->subscription_id)->first()) {
                    return response()->json(['receive' => "0"]);
                }
                // Create subscription in database
                $subscription = UserSubscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $request->client_field_2,
                    'subscription_id' => $request->subscription_id,
                    'card_id' => $request->card_id,
                    'start_date' => $request->client_field_3,
                    'status' => 'active'
                ]);
                $user->update([
                    'current_plan' => $request->client_field_2
                ]);
                return response()->json(['receive' => "0"]);
            }
            if ($request->status == 'CAPTURED') {
                $subscription = UserSubscription::where('subscription_id', $request->subscription_id)->first();
                if (!$subscription) {
                    sleep(10);
                    $subscription = UserSubscription::where('subscription_id', $request->subscription_id)->first();
                }
                if ($subscription) {
                    $plan = Plan::find($subscription->plan_id);
                    $user = User::find($subscription->user_id);

                    if (Payment::where('order_id', $request->order_id)->first()) {
                        return response()->json(['receive' => "0"]);
                    }
                    $payment = Payment::create([
                        'user_id' => $user->id,
                        'amount' => $request->amount,
                        'order_id' => $request->order_id,
                        'access_id' => $request->access_id,
                        'coupon_id' => 0,
                        'pay_type' => 'subscription',
                        'point_id' => $plan->id,
                        'status' => 1,
                    ]);
                    $add_point = $plan->point;

                    if ((int)$request->amount < $plan->amount) {
                        
                        $last_subscription = UserSubscription::where('user_id', $user->id)
                            ->where('subscription_id', '!=', $subscription->subscription_id)
                            ->orderByDesc('created_at')->first();

                        if ($last_subscription) {
                            $last_plan = Plan::find($last_subscription->plan_id);
                            $add_point = max(0, $add_point - $last_plan?->point);
                        }
                        else {
                            $add_point = (int)$request->amount;
                        }
                    }
                    
                    if ($add_point > 0) {
                        (new PointHistoryController)->create($user->id, $user->point, $add_point, 'subscription', $payment->id);
                        $user->increment('point', $add_point);
                    }
                    return response()->json(['receive' => "0"]);
                }
                return response()->json(['receive' => "1"]);
            }
            
        }

        if (isset($request->pay_type) && isset($request->status)) {
            $point = Point::find($request->client_field_2);
            $pt_amount = $point->point;

            $user = User::find($request->client_field_1);

            if ($point && $user) {
                if (str_ends_with($request->event, 'regist')) {
                    $coupon_id = $request->client_field_3;
                    $coupon = Coupon::where('id', $coupon_id)->where('type', 'DISCOUNT')->first();
                    if ($coupon) {
                        if ($coupon->expiration <= date('Y-m-d H:i:s')) {
                            $coupon_id = 0;
                        }
                        $records = Coupon_record::where('coupon_id', $coupon->id)->where('user_id', $user->id)->count();
                        if ($records >= $coupon->user_limit) {
                            $coupon_id = 0;
                        }
                        $total_records = Coupon_record::where('coupon_id', $coupon->id)->count();
                        if ($total_records >= $coupon->count) {
                            $coupon_id = 0;
                        }
                    }
                    else {
                        $coupon_id = 0;
                    }
                    if ($coupon_id > 0) {
                        $coupon_id = Coupon_record::create([
                            'coupon_id' => $coupon_id,
                            'user_id' => $user->id,
                        ])->id;
                    }
                    Payment::Create([
                        'user_id' => $user->id,
                        'point_id' => $point->id,
                        'access_id' => $request->access_id,
                        'order_id' => $request->order_id,
                        'pay_type' => $request->pay_type,
                        'coupon_id' => $coupon_id,
                        'amount' => intval($request->amount),
                    ]);
                }
                else if ($request->status == 'CAPTURED') {
                    $payment = Payment::where('order_id', $request->order_id)->first();
                    if (!$payment) {
                        return response()->json(['receive' => "1"]);
                    }
                    if ($payment->status == 1) {
                        return response()->json(['receive' => "0"]);
                    }
                    $rank = Rank::where('rank', $user->current_rank)->first();
                    $pt_rate = 0;
                    if ($rank) $pt_rate = $rank -> pt_rate / 100;

                    if ($point->amount != intval($request->amount)) {
                        if ($payment->coupon_id == 0) {
                            $pt_amount = $request->amount;
                            $pt_rate = 0;
                        }
                        if ($request->pay_type == 'Virtualaccount' && $request->amount != $request->billing_amount) {
                            $pt_amount = $request->amount;
                            $pt_rate = 0;
                        }
                    }
                    if ($request->pay_type == 'Virtualaccount') {
                        $payment->update(['amount' => intval($request->amount)]);
                    }
                    $payment->update(['status' => 1]);
                        
                    $add_point = $pt_amount + (int)($point->point * $pt_rate);
                    (new PointHistoryController)->create($user->id, $user->point, $add_point, 'purchase', $payment->id);
                    
                    $user->increment('point', $add_point);
                    
                    $this->add_invited_bonus($user);
                }
            }

            return response()->json(['receive' => "0"]);
        }
        else {
            return response()->json(['receive' => "1"]);
        }
    }

    public function webhook_gmo(Request $request) {
        Log::info('GMO Webhook', $request->all());
        return response()->json(['receive' => "0"]);
    }

    public function apple_pay_validate(Request $request) {
        $ch = curl_init();
        $data = [
            'merchantIdentifier' => 'merchant.com.reve-oripa', // Your Merchant ID
            'displayName' => 'reve-oripa',
            'initiative' => 'web',
            'initiativeContext' => 'reve-oripa.jp'
        ];
    
        $payload = json_encode($data);
    
        $headers = [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ];
    
        curl_setopt($ch, CURLOPT_URL, $request->validationURL);
        curl_setopt($ch, CURLOPT_SSLCERT, config('services.apple_pay.cert_path'));
        curl_setopt($ch, CURLOPT_SSLKEY, config('services.apple_pay.key_path'));
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            return ['error' => 'Validation request failed'];
        }

        curl_close($ch);
    
        return $response;
    }

    public function fincode_success(Request $request) {
        return redirect()->route('purchase_success');
    }

    public function fincode_cancel(Request $request) {
        return redirect()->route('user.point');
    }

    public function captureCharge($chargeId, $amount)
    {
        // Set Amazon Pay config
        $this->set_amazonpay_config();

        $client = new Amazon\Pay\API\Client($this->amazonpay_config);

        try {
            // Prepare the capture payload
            $payload = [
                'captureAmount' => $amount,
                'softDescriptor' => 'reve-oripa'
            ];

            $headers = array('x-amz-pay-Idempotency-Key' => uniqid());
            // Call captureCharge
            $result = $client->captureCharge($chargeId, $payload, $headers);

            // Handle response
            if ($result['status'] === 200) {
                $response = json_decode($result['response'], true);
                Log::info('Amazon Pay Capture Charge', $response);
                return $response;
            } else {
                Log::error('Amazon Pay Capture failed', ['status' => $result['status'], 'response' => $result['response']]);
                return [];
            }

        } catch (\Exception $e) {
            Log::error('Amazon Pay Capture exception', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function getChargeDetails($chargeId)
    {
        // Set Amazon Pay config
        $this->set_amazonpay_config();

        $sandbox = env('AMAZON_PAY_SANDBOX', true);
        $client = new Amazon\Pay\API\Client($this->amazonpay_config);

        try {
            // This returns an associative array with body content already parsed
            $result = $client->getCharge($chargeId);

            if ($result['status'] === 200) {
                $response = json_decode($result['response'], true);
                Log::info('Amazon Pay Charge Details ['.$response['statusDetails']['state'].']', $response);
                if ($response['statusDetails']['state'] === 'Authorized') {
                    $customInfomation = json_decode($response['merchantMetadata']['customInformation']);
                    $user_id = $customInfomation[0];
                    $point_id = $customInfomation[1];
                    $coupon_id = $customInfomation[2];
                    $coupon = Coupon::where('id', $coupon_id)->where('type', 'DISCOUNT')->first();
                    if ($coupon) {
                        if ($coupon->expiration <= date('Y-m-d H:i:s')) {
                            $coupon_id = 0;
                        }
                        $records = Coupon_record::where('coupon_id', $coupon_id)->where('user_id', $user_id)->count();
                        if ($records >= $coupon->user_limit) {
                            $coupon_id = 0;
                        }
                        $total_records = Coupon_record::where('coupon_id', $coupon_id)->count();
                        if ($total_records >= $coupon->count) {
                            $coupon_id = 0;
                        }
                    }
                    else {
                        $coupon_id = 0;
                    }
                    if ($coupon_id > 0) {
                        $coupon_id = Coupon_record::create([
                            'coupon_id' => $coupon_id,
                            'user_id' => $user_id,
                        ])->id;
                    }
                    Payment::Create([
                        'user_id' => $user_id,
                        'point_id' => $point_id,
                        'access_id' => $chargeId,
                        'order_id' => $chargeId,
                        'pay_type' => 'amazon',
                        'coupon_id' => $coupon_id,
                        'amount' => intval($response['chargeAmount']['amount']),
                    ]);
                    $this->captureCharge($chargeId, $response['chargeAmount']);
                }
                else if ($response['statusDetails']['state'] === 'Declined') {
                    // Handle declined state
                }
                else if ($response['statusDetails']['state'] === 'Canceled') {
                    // Handle canceled state
                }
                else if ($response['statusDetails']['state'] === 'Captured') {
                    $payment = Payment::where('order_id', $chargeId)->first();
                    if (!$payment) {
                        $customInfomation = json_decode($response['merchantMetadata']['customInformation']);
                        $user_id = $customInfomation[0];
                        $point_id = $customInfomation[1];
                        $coupon_id = $customInfomation[2];
                        $coupon = Coupon::where('id', $coupon_id)->where('type', 'DISCOUNT')->first();
                        if ($coupon) {
                            if ($coupon->expiration <= date('Y-m-d H:i:s')) {
                                $coupon_id = 0;
                            }
                            $records = Coupon_record::where('coupon_id', $coupon_id)->where('user_id', $user_id)->count();
                            if ($records >= $coupon->user_limit) {
                                $coupon_id = 0;
                            }
                            $total_records = Coupon_record::where('coupon_id', $coupon_id)->count();
                            if ($total_records >= $coupon->count) {
                                $coupon_id = 0;
                            }
                        }
                        else {
                            $coupon_id = 0;
                        }
                        if ($coupon_id > 0) {
                            $coupon_id = Coupon_record::create([
                                'coupon_id' => $coupon_id,
                                'user_id' => $user_id,
                            ])->id;
                        }
                        $payment = Payment::Create([
                            'user_id' => $user_id,
                            'point_id' => $point_id,
                            'access_id' => $chargeId,
                            'order_id' => $chargeId,
                            'pay_type' => 'amazon',
                            'coupon_id' => $coupon_id,
                            'amount' => intval($response['captureAmount']['amount']),
                        ]);
                    }
                    if ($payment->status == 1) {
                        return true;
                    }
                    $user = User::find($payment->user_id);
                    $point = Point::find($payment->point_id);
                    $pt_amount = $point->point;
                    if (!$user || !$point) {
                        return false;
                    }
                    $rank = Rank::where('rank', $user->current_rank)->first();
                    $pt_rate = 0;
                    if ($rank) $pt_rate = $rank -> pt_rate / 100;

                    if ($point->amount != intval($response['captureAmount']['amount'])) {
                        if ($payment->coupon_id == 0) {
                            $pt_amount = intval($response['captureAmount']['amount']);
                            $pt_rate = 0;
                        }
                    }
                    $payment->update(['status' => 1]);
                    $add_point = $pt_amount + (int)($point->point * $pt_rate);
                    (new PointHistoryController)->create($user->id, $user->point, $add_point, 'purchase', $payment->id);
                    
                    $user->increment('point', $add_point);

                    $this->add_invited_bonus($user);
                }
                return true;
            }
        } catch (\Exception $e) {
        }
        return false;
    }
    
    public function handleAmazonpayIPN(Request $request) {
        $rawBody = $request->getContent();

        // Decode the raw SNS notification
        $data = json_decode($rawBody, true);

        if (!isset($data['Message'])) {
            Log::warning('Amazon Pay IPN missing Message key');
            return response('Invalid IPN', 400);
        }

        // Decode the inner Message JSON
        $message = json_decode($data['Message'], true);

        Log::info('Amazon Pay IPN Parsed Message', $message);

        // Check if this is a state change for a charge object
        if (
            isset($message['NotificationType']) &&
            $message['NotificationType'] === 'STATE_CHANGE' &&
            $message['ObjectType'] === 'CHARGE'
        ) {
            $chargeId = $message['ObjectId'] ?? null;

            if ($chargeId) {
                $result = $this->getChargeDetails($chargeId);
                if ($result) {
                    Log::info('Amazon Pay IPN Charge Details succeed', ['chargeId' => $chargeId]);
                } else {
                    Log::error('Amazon Pay IPN Charge Details failed', ['chargeId' => $chargeId]);
                    return response('Invalid IPN', 400);
                }
            }
        }

        return response('OK', 200);
    }
}
