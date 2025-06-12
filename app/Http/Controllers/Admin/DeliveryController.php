<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\User;
use App\Models\Product;
use App\Models\Product_log;
use App\Models\Profile;
use App\Http\Controllers\PointHistoryController;

use App\Http\Resources\ProductListResource;
use App\Http\Resources\DeliveryProductResource;

use Str;
use Carbon\Carbon;
use Mail;
use DB;

class DeliveryController extends Controller
{
    public function __construct() {
        $this->page_size = 30;
    }

    public function admin (Request $request) {
        
        $name = $request->name ? $request->name : "";

        $products = Product_log::select(
            'product_logs.id as id',
            'product_logs.name as name',
            'product_logs.point as point',
            'product_logs.image as image',
            'product_logs.updated_at as updated_at',
            'product_logs.status as status',
            'product_logs.rare as rare',
            'product_logs.user_id as user_id',
            'product_logs.gacha_title as gacha_title',
            DB::raw('concat(profiles.first_name, profiles.last_name) as user_name'),
            DB::raw('concat(profiles.prefecture, profiles.city, IFNULL(profiles.street,""), IF(profiles.building IS NULL, "", CONCAT("　", profiles.building))) as address'),
            'users.email as email'
        )->leftJoin('profiles', function($join) { $join->on('product_logs.user_id', '=', 'profiles.user_id'); })
        ->leftJoin('users', function($join) { $join->on('product_logs.user_id', '=', 'users.id'); })
        ->where('product_logs.status', 3);

        $totalProducts = $products->count();
        $totalPoints = (int)$products->sum('product_logs.point');

        $products = $products
        ->where(function($query) use ($name) {
            $query->where(DB::raw('concat(profiles.first_name, profiles.last_name)'), 'like', '%'.$name.'%')
            ->orWhere('users.email', 'like', '%'.$name.'%')
            ->orWhere('profiles.phone', 'like', '%'.$name.'%')
            ->orWhere('product_logs.name', 'like', '%'.$name.'%')
            ->orWhere('product_logs.gacha_title', 'like', '%'.$name.'%');
        });

        $searchProducts = $products->count();
        $searchPoints = (int)$products->sum('product_logs.point');

        $products = $products->orderBy('product_logs.user_id')
        ->orderBy('updated_at', 'ASC')->get();

        $products = DeliveryProductResource::collection($products);
        $hide_cat_bar = 1;
        $search_cond = [
            "name" => $name,
        ];
        return inertia('Admin/Delivery/Index', compact('hide_cat_bar', 'products', 'search_cond', 'totalProducts', 'totalPoints', 'searchProducts', 'searchPoints'));
    }

    public function acquired (Request $request) {
        $logs = Product_log::where('status', 1)->whereRaw('updated_at < NOW() - INTERVAL 7 DAY')->get();
  
        foreach($logs as $log) {
            $log->status = 2;
            $log->save();
            if ($product = Product::find($log->product_id)) {
                if ($product->is_lost_product > 0)
                    $product->increment('marks');
            }
            $user = User::find($log->user_id);
            if ($user) (new PointHistoryController)->create($user->id, $user->point, $log->point, 'exchange', $log->id);
            $user?->increment('point', $log->point);
        }

        $name = $request->name ? $request->name : "";

        $products = Product_log::select(
            'product_logs.id as id',
            'product_logs.name as name',
            'product_logs.point as point',
            'product_logs.image as image',
            'product_logs.updated_at as updated_at',
            'product_logs.status as status',
            'product_logs.rare as rare',
            'product_logs.user_id as user_id',
            'product_logs.gacha_title as gacha_title',
            DB::raw('concat(profiles.first_name, profiles.last_name) as user_name'),
            DB::raw('concat(profiles.prefecture, profiles.city, IFNULL(profiles.street,""), IF(profiles.building IS NULL, "", CONCAT("　", profiles.building))) as address'),
            'users.email as email'
        )->leftJoin('profiles', function($join) { $join->on('product_logs.user_id', '=', 'profiles.user_id'); })
        ->leftJoin('users', function($join) { $join->on('product_logs.user_id', '=', 'users.id'); })
        ->where('product_logs.status', 1)
        ->where('product_logs.rare', '!=', 'ポイント');

        $totalProducts = $products->count();
        $totalPoints = (int)$products->sum('product_logs.point');

        $products = $products
        ->where(function($query) use ($name) {
            $query->where(DB::raw('concat(profiles.first_name, profiles.last_name)'), 'like', '%'.$name.'%')
            ->orWhere('users.email', 'like', '%'.$name.'%')
            ->orWhere('profiles.phone', 'like', '%'.$name.'%')
            ->orWhere('product_logs.name', 'like', '%'.$name.'%')
            ->orWhere('product_logs.gacha_title', 'like', '%'.$name.'%');
        });

        $searchProducts = $products->count();
        $searchPoints = (int)$products->sum('product_logs.point');

        $products = $products->orderBy('product_logs.user_id')
        ->orderBy('updated_at', 'ASC')->get();

        $products = DeliveryProductResource::collection($products);
        $hide_cat_bar = 1;
        $search_cond = [
            "name" => $name,
        ];
        return inertia('Admin/Delivery/Acquired', compact('hide_cat_bar', 'products', 'search_cond', 'totalProducts', 'totalPoints', 'searchProducts', 'searchPoints')) ; 
    }

    public function getProductData(Request $request) {
        if (isset($request->user_id)) {
            $products = Product_log::where('user_id', $request->user_id)->where('status', 3)->get();
            $res = ['status' =>1 ];
            if(count($products) > 0) {
                $user = $products[0]->user;
                $profile = $products[0]->profile;
                $res['user'] = $user;
                $res['profile'] = $profile;
                $res['products'] = DeliveryProductResource::collection($products);
            } else {
                $res = ['status' => 0];
            }
            return $res;
        }
        $id = $request->id;
        $product = Product_log::find($id);
        $res = ['status' =>1 ];
        if($product) {
            $user = $product->user;
            $profile = $product->profile;
            $res['user'] = $user;
            $res['profile'] = $profile;
        } else {
            $res = ['status' =>0 ];
        }
        return $res;
    }

    public function deliver_post(Request $request) {
        $user_id = $request->user_id;
        $checks = $request->checks;
        $products = Product_log::where('user_id', $user_id)->where('status', 3)->get();

        $count = 0;
        foreach($products as $product) {
            $key = "id" . $product->id;
            if (isset($checks[$key]) && $checks[$key]) {
                $product->status = 4;
                $product->tracking_number = $request->tracking_number;
                $count += 1;
                $product->save();
            }
        }

//         if ($count > 0) {
//             $email = User::find($user_id)->email;

//             $content = "<center><img src='https://reve-oripa.jp/images/logo.png' style='width:100%; max-width:300px;'></center>
// <p>この度は「イブガチャ」をご利用いただき、誠にありがとうございます。<br/>
// お客様より発送依頼をいただきました商品を、本日発送いたしました。<br/><br/>
// 追跡番号: {$request->tracking_number}<br/>
// </p>";
//             Mail::send([], [], function ($message) use ($email, $content)
//             {
//                 $message->to($email)
//                     ->subject('イブガチャ 発送完了のお知らせ')
//                     ->from(env('MAIL_FROM_ADDRESS'), 'イブガチャ')
//                     ->html($content);
//             });
//         }
        return redirect()->back()->with('message', '発送済みにしました！')->with('title', '発送')->with('type', 'dialog');
    }

    public function completed (Request $request) {
        $page_size = $this->page_size;
        $page = 1;
        if (isset($request->page)) $page = intval($request->page);
        
        $name = $request->name ? $request->name : "";

        $products = Product_log::select(
            'product_logs.id as id',
            'product_logs.name as name',
            'product_logs.point as point',
            'product_logs.image as image',
            'product_logs.updated_at as updated_at',
            'product_logs.status as status',
            'product_logs.rare as rare',
            'product_logs.user_id as user_id',
            'product_logs.gacha_title as gacha_title',
            'product_logs.tracking_number as tracking_number',
            DB::raw('concat(profiles.prefecture, profiles.city, IFNULL(profiles.street,""), IF(profiles.building IS NULL, "", CONCAT("　", profiles.building))) as address'),
        )->leftJoin('profiles', function($join) { $join->on('product_logs.user_id', '=', 'profiles.user_id'); })
        ->leftJoin('users', function($join) { $join->on('product_logs.user_id', '=', 'users.id'); })
        ->where('product_logs.status', 4);

        $totalProducts = $products->count();
        $totalPoints = (int)$products->sum('product_logs.point');

        $products = $products
        ->where(function($query) use ($name) {
            $query->where(DB::raw('concat(profiles.first_name, profiles.last_name)'), 'like', '%'.$name.'%')
            ->orWhere('users.email', 'like', '%'.$name.'%')
            ->orWhere('profiles.phone', 'like', '%'.$name.'%')
            ->orWhere('product_logs.name', 'like', '%'.$name.'%')
            ->orWhere('product_logs.gacha_title', 'like', '%'.$name.'%');
        });
        
        $searchProducts = $products->count();
        $searchPoints = (int)$products->sum('product_logs.point');
        $total = ceil($searchProducts / $page_size);
        
        $products = $products->orderBy('updated_at', 'DESC')
        ->offset(($page-1)*$page_size)
        ->limit($page_size)->get();

        $products = DeliveryProductResource::collection($products);
        $hide_cat_bar = 1;
        
        $search_cond = [
            "name" => $name,
            "page" => $page,
        ];

        return inertia('Admin/Delivery/Completed', compact('hide_cat_bar', 'products', 'search_cond', 'total', 'totalProducts', 'totalPoints', 'searchProducts', 'searchPoints'));
    }

    public function unDeliver_post(Request $request) {
        $id = $request->id;
        $product = Product_log::find($id);
        $product->tracking_number = null;
        $product->status = 3;   // into waiting status
        $product->save();
        return redirect()->back()->with('message', '未発送にしました！')->with('title', '発送')->with('type', 'dialog');
    }


    public function csv_delivery(Request $request) {
        $hide_cat_bar = 1;
        return inertia('Admin/Delivery/CSV', compact('hide_cat_bar')) ; 
    }

    public function csv_delivery_post(Request $request) {
        $rules = [
            'checks' => 'required',
        ];
        $validatored = $request->validate($rules);

        $checks = $validatored['checks'];
        
        $selectedUserIds = [];
        foreach ($checks as $key => $value) {
            if ($value) {
                // Extract user_id from the key (e.g. 'id123' -> 123)
                $userId = substr($key, 2);
                $selectedUserIds[] = $userId;
            }
        }


        $users = Profile::whereIn('user_id', $selectedUserIds)->get();

        $columnNames = [
            'お客様管理番号',
            '送り状種別',
            '温度区分',
            '予備4',
            '出荷予定日',
            '配達指定日',
            '配達時間帯区分',
            '届け先コード',
            '届け先電話番号',
            '届け先電話番号(枝番)',
            '届け先郵便番号',
            '届け先住所',
            'お届け先建物名（ｱﾊﾟｰﾄﾏﾝｼｮﾝ名）',
            '会社・部門名１',
            '会社・部門名２',
            '届け先名',
            '届け先名(カナ)',
            '敬称',
            '依頼主コード',
            '依頼主電話番号',
            '依頼主電話番号(枝番)',
            '依頼主郵便番号',
            '依頼主住所',
            '依頼主建物名（ｱﾊﾟｰﾄﾏﾝｼｮﾝ名）',
            '依頼主名（漢字）',
            '依頼主名(カナ)',
            '品名コード１',
            '品名１',
            '品名コード２',
            '品名2',
            '荷扱い１',
            '荷扱い２',
            '記事',
            'コレクト代金引換額(税込)',
            'コレクト内消費税額',
            '営業所止置き',
            '止め置き営業所コード',
            '発行枚数',
            '個数口枠の印字',
            '請求先顧客コード',
            '請求先分類コード',
            '運賃管理番号',
        ];
        
        $outputs = '';
        foreach ($columnNames as $item) {
            $outputs .= $item . ',';
        }
        $outputs = rtrim($outputs, ',') . "\n";

        foreach ($users as $item) {
            if (isset($checks['id'.$item->user_id]) && $checks['id'.$item->user_id]) {
                $arrInfo = [
                    '',
                    '7',
                    '',
                    '',
                    now()->format('Y/m/d'),
                    '',
                    '',
                    '',
                    $item->phone,
                    '',
                    $item->postal_code,
                    $item->prefecture.$item->city.$item->street,
                    $item->building,
                    '',
                    '',
                    $item->first_name . ' '. $item->last_name,
                    '',
                    '',
                    '',
                    '08088357446',
                    '',
                    '2600014',
                    '千葉県千葉市中央区本千葉町６番１号',
                    'エレル千葉中央駅前ビル４０１号室',
                    'イブガチャ',
                    '',
                    '',
                    '玩具',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '',
                    '09025282896',
                    '',
                    '01',
                ];
                foreach ($arrInfo as $item) {
                    $outputs .= $item . ',';
                }
                $outputs = rtrim($outputs, ',') . "\n";
            }
        }
        $txt2 = pack('C*',0xEF,0xBB,0xBF). $outputs;
        $fileName = date('Y_m_d') .'_'. uniqid() . '.csv';
        $save_path = 'delivery_csv/' . $fileName;
        if (!file_exists('delivery_csv')) {
            mkdir('delivery_csv', 0777, true);
        }
        file_put_contents($save_path, $txt2);
        return response()->streamDownload(function () use ($save_path) {
            readfile($save_path);
        }, $fileName, [
            'Content-Type' => 'text/csv',
        ]);
        
        $info = array(
            'name' => "reve-oripa"
        );
        Mail::send('delivery_list', $info, function ($message) use ($save_path, $email)
        {
            $message->to($email)
                ->subject('発送依頼一覧');
            $message->attach(getcwd(). "/" . $save_path);
            $message->from(env('MAIL_FROM_ADDRESS'), 'reve-oripa');
        });

        return redirect()->back()->with('message', '送信しました！')->with('title', '発送依頼一覧')->with('type', 'dialog');
    }

}
