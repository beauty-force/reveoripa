<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\PointCreate;

use App\Http\Resources\PointList;

use App\Models\Category;
use App\Models\Point;
use App\Models\User;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Coupon;
use App\Models\Coupon_record;
use App\Models\Gacha_video;
use App\Models\Gacha_record;
use App\Models\Point_history;
use App\Http\Controllers\PointHistoryController;
use Carbon\Carbon;
use App\Models\Rank;
use App\Models\Ranking_bonus;
use App\Models\Ranking_history;
use App\Models\Product_log;

use ImageResize;
use Str;

use Illuminate\Support\Facades\Validator;

use Illuminate\Support\Facades\DB;

class AdminController extends Controller
{
    public function index(Request $request) {
        // return inertia('Admin/Index');
        return redirect(route('admin.gacha'));
    }

    public function point_create(Request $request) {
        $hide_cat_bar = 1;
        return inertia('Admin/PointCreate', compact('hide_cat_bar')); 
    }

    public function Point_list(Request $request) {
        $points = Point::orderBy('amount', 'ASC')->get();
        $points = PointList::collection($points);
        $hide_cat_bar = 1;
        return inertia('Admin/PointList', compact('points', 'hide_cat_bar'));
    }

    public function point_store(PointCreate $request) {
        $request->validated();
        $name = saveImage('images/point', $request->file('image'), false);
        $data = array('title'=>$request->title, 'point'=>$request->point, 'amount'=>$request->amount, 'image'=>$name);
        Point::create($data);
        return redirect(route('admin.point'))->with('message', '追加しました！')->with('title', 'ポイント購入')->with('type', 'dialog');
        
    }

    public function point_edit(Request $request, $id) {

        $point = Point::find($id);
        $point->image = getPointImageUrl($point->image);
        $hide_cat_bar = 1;
        return inertia('Admin/PointEdit', compact('point', 'hide_cat_bar'));
    }

    public function point_update(Request $request) {
        if($request->image) {
             $validatored = $request->validate([
                'title' => 'required',
                'point' => 'required',
                'amount' => 'required|numeric|min:100',
                'image' => 'required|image|mimes:jpeg,jpg,png,gif,svg|max:2048',
            ]);
            $name = saveImage('images/point', $request->file('image'), false);
            $data = array('title'=>$request->title, 'amount'=>$request->amount, 'point'=>$request->point, 'image'=>$name);
            
        } else {
            $validatored = $request->validate([
                'title' => 'required',
                'point' => 'required',
                'amount' => 'required|numeric|min:100',
            ]);
            $data = array('title'=>$request->title, 'amount'=>$request->amount, 'point'=>$request->point);
        }
        
        $point = Point::find($request->id);
        $point->update($data);
        return redirect(combineRoute(route('admin.point'), $request->category_id))->with('message', '編集しました！')->with('title', 'ポイント購入管理')->with('type', 'dialog');
    }

    public function point_destroy($id = null) {
        Point::where("id", $id)->delete();
        return redirect()->back()->with('message', '削除しました！')->with('title', 'ポイント購入管理')->with('type', 'dialog');
    }


    public function delivery_list() {
        return inertia('Admin/DeliveryList');
    }

    public function category() {
        return inertia('Admin/CategoryList');
    }

    public function banner() {
        $hide_cat_bar = 1;
        $banners = DB::table('banner')->orderBy('order')->get();
        return inertia('Admin/Banner', compact('hide_cat_bar', 'banners'));
    }

    public function banner_create(Request $request) {
        $file = $request->file('file');
        if ($file) $image = '/images/banner/'.saveImage('images/banner', $file, false);
        else $image = "";
        DB::table('banner')->insert([
            'link_url' => $request->link_url ?? '#',
            'image' => $image
        ]);
        return redirect()->back()->with('message', '追加しました！')->with('title', 'バナー設定')->with('type', 'dialog');
    }

    public function banner_store(Request $request) {
        $files = $request->file('files');
        $order_level = 0;
        foreach($request->banners as $item) {
            $data = [
                'order'=>$order_level,
                'link_url'=>$item['link_url'] ?? '#'
            ];
            if (isset($files[$order_level])) {
                $image = '/images/banner/'.saveImage('images/banner', $files[$order_level], false);
                $data['image'] = $image;
            }
            DB::table('banner')->where('id', $item['id'])->update($data);
            $order_level += 1;
        }
        return redirect()->back()->with('message', '保存しました！')->with('title', 'バナー編集')->with('type', 'dialog');
    }

    public function banner_destroy($id = null) {
        $banner = DB::table('banner')->find($id);
        if ($banner && $banner->image != '' && file_exists(public_path($banner->image))) unlink(public_path($banner->image));
        DB::table('banner')->where("id", $id)->delete();
        return redirect()->back()->with('message', '削除しました！')->with('title', 'バナー編集')->with('type', 'dialog');
    }

    public function users (Request $request) {
        $page_size = 30;
        $keyword = $request->keyword ? $request->keyword : "";
        $page = $request->page ? intval($request->page) : 1;
        $order_by = $request->order_by ? $request->order_by : "amount";

        $search_cond = [
            'page' => $page,
            'keyword' => $keyword,
            'order_by' => $order_by,
        ];

        $direction = $order_by == 'status' ? 'asc' : 'desc';
        
        // if ($order_by == 'amount') {
            $purchases = DB::table('payments')
            ->select('user_id', DB::raw('sum(amount) as amount'))
            ->where('status', 1)
            ->groupBy('user_id');
        // }
        
        $users = DB::table('users')->leftJoin('ranks', 'users.current_rank', '=', 'ranks.rank')
        ->leftJoinSub($purchases, 'purchases', function($join) {
            $join->on('users.id', '=', 'purchases.user_id');
        })
        ->select('users.id', 'users.name', 'users.email', 'users.point', DB::raw('LPAD(users.phone, 11, "0") as phone'), 'users.status', 'purchases.amount', 'ranks.title as rank', 'users.line_id');

        // if ($order_by == 'amount') {
        //     $users = $users->leftJoinSub($purchases, 'purchases', function($join) {
        //         $join->on('users.id', '=', 'purchases.user_id');
        //     })->select('users.*', 'purchases.amount');
        // }

        $users = $users->where('type', 0)->where('status', '<', 2)->where(function($query) use ($keyword) {
            $query->where('users.email', 'like', $keyword)
            ->orWhere('users.phone', 'like', '%'.$keyword.'%')
            ->orWhere('users.name', 'like', '%'.$keyword.'%')
            ->orWhere('users.invite_code', 'like', $keyword);
        });
        
        $total = $users->count();
        $total = ceil($total / $page_size);
        if ($total == 0) $total = 1;
        if ($page > $total) $page = $total;

        if ($order_by == 'rank') $order_by = 'current_rank';
        $order_by = ($order_by == 'amount' ? 'purchases.' : 'users.').$order_by;

        $users = $users->orderBy($order_by, $direction)->offset(($page - 1) * $page_size)->limit($page_size)->get();

        $hide_cat_bar = 1;

        $total_point = (int)User::where('type', 0)->sum('point');

        return inertia('Admin/Users/Index', compact('users', 'total', 'search_cond', 'hide_cat_bar', 'total_point'));
    }

    public function exportUsers(Request $request) {
        $purchases = DB::table('payments')
            ->select('user_id', DB::raw('sum(amount) as purchase_amount'))
            ->where('status', 1)
            ->groupBy('user_id');

        $users = User::select(
            'users.id',
            'users.name', 
            'users.email',
            'users.phone',
            'users.point',
            'users.dp',
            'users.status',
            'users.created_at',
            'profiles.first_name',
            'profiles.last_name',
            'profiles.first_name_gana', 
            'profiles.last_name_gana',
            'profiles.postal_code',
            'profiles.prefecture',
            'profiles.city',
            'profiles.street',
            'profiles.building',
            'ranks.title as rank_title',
            'purchases.purchase_amount'
        )
        ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
        ->leftJoin('ranks', 'users.current_rank', '=', 'ranks.rank')
        ->leftJoinSub($purchases, 'purchases', function($join) {
            $join->on('users.id', '=', 'purchases.user_id');
        })
        ->orderBy('purchases.purchase_amount', 'desc')
        ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="users_' . date('Y-m-d-H-i-s') . '.csv"',
        ];

        $callback = function() use($users) {
            $file = fopen('php://output', 'w');
            // Add BOM for UTF-8
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($file, [
                'ID',
                '名前',
                'メールアドレス',
                '電話番号', 
                '氏名',
                '氏名(カナ)',
                '住所',
                'ポイント',
                'RP',
                '購入金額',
                'ランク',
                '登録日時',
                'ステータス',
            ]);

            foreach ($users as $user) {
                $full_name = $user->first_name . ' ' . $user->last_name;
                $full_name_gana = $user->first_name_gana . ' ' . $user->last_name_gana;
                $full_address = '〒' . $user->postal_code . ' ' . $user->prefecture . ' ' . $user->city . ' ' . $user->street . ' ' . $user->building;
                
                $status = match($user->status) {
                    0 => '停止中',
                    1 => '通常',
                    2 => '削除済み',
                    default => '不明'
                };

                fputcsv($file, [
                    $user->id,
                    $user->name,
                    $user->email,
                    "\u{200B}" . $user->phone, // Zero-width space to force string
                    $full_name,
                    $full_name_gana,
                    $full_address, 
                    $user->point,
                    $user->dp,
                    $user->purchase_amount ?? 0,
                    $user->rank_title,
                    $user->created_at->format('Y-m-d H:i:s'),
                    $status,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function user_detail($id) {
        $user = User::find($id);
        $profile = Profile::where('user_id', $id)->first();
        $payments = DB::table('point_history')->leftJoin('payments', 'payments.id', '=', 'point_history.ref_id')
            ->leftJoin('coupon_records', 'payments.coupon_id', '=', 'coupon_records.id')
            ->leftJoin('coupons', 'coupons.id', '=', 'coupon_records.coupon_id')
            ->where('point_history.user_id', $id)
            ->where(function($query) {
                $query->where('point_history.point_type', 'purchase')
                ->orWhere('point_history.point_type', 'subscription');
            })
            ->select('point_history.point_diff as point', 'point_history.updated_at', 'payments.order_id', 'payments.amount', 'coupons.title as coupon_name')
            ->orderBy('point_history.updated_at', 'desc')->get();

        // $payments = DB::table('payments')->leftJoin('points', 'payments.point_id', '=', 'points.id')
        // ->where('payments.user_id', $id)
        // ->where('payments.status', 1)
        // ->select('payments.order_id', 'points.point', 'points.amount', 'payments.updated_at')
        // ->orderBy('payments.updated_at', 'desc')
        // ->get();
        
        $coupons = DB::table('coupon_records')->leftJoin('coupons', 'coupon_records.coupon_id', '=', 'coupons.id')
        ->where('coupon_records.user_id', $id)
        ->where('coupons.type', 'NORMAL')
        ->select('coupons.title', 'coupons.code', 'coupons.point', 'coupon_records.updated_at')
        ->orderBy('coupon_records.updated_at', 'desc')
        ->get();
        $hide_cat_bar = 1;

        $point_types = [
            'gacha' => 'ガチャ',
            'purchase' => '購入',
            'exchange' => 'PTに換算',
            'coupon' => 'クーポン',
            'admin' => '管理者が付与',
            'invite' => '友達紹介',
            'invited' => '紹介で登録',
            'invite_payment' => '友達が購入時のボーナス',
            'deliveryFee' => '送料支払',
            'reset' => 'リセット',
            'rank_up' => 'ランクアップ',
            'daily_bonus' => '日次ランキング',
            'weekly_bonus' => '週次ランキング',
            'monthly_bonus' => '月次ランキング',
            'subscription' => 'サブスクリプション',
        ];
        $point_logs = Point_history::select('point_type', 'point_diff', 'updated_at', 'point_before')
        ->where('user_id', $id)->orderBy('id', 'desc')->get();
        
        $grouped_logs = [];
        foreach($point_logs as $log) {
            if ($log->point_type === 'exchange') {
                $key = $log->updated_at->format('Y-m-d H:i:s');
                if (!isset($grouped_logs[$key])) {
                    $grouped_logs[$key] = [
                        'point_type' => $point_types[$log->point_type],
                        'point_diff' => 0,
                        'updated_at' => $log->updated_at,
                        'point_before' => $log->point_before,
                        'updated_at1' => $log->updated_at->format('Y/n/j H:i:s')
                    ];
                }
                $grouped_logs[$key]['point_diff'] += $log->point_diff;
                $grouped_logs[$key]['point_before'] = $log->point_before;
            } else {
                $grouped_logs[] = [
                    'point_type' => $point_types[$log->point_type],
                    'point_diff' => $log->point_diff,
                    'updated_at' => $log->updated_at,
                    'point_before' => $log->point_before,
                    'updated_at1' => $log->updated_at->format('Y/n/j H:i:s')
                ];
            }
        }
        $point_logs = collect($grouped_logs)->values();
        $show_limit = 20;
        $rank = Rank::where('rank', $user->current_rank)->first()?->title;

        return inertia('Admin/Users/Detail', compact('user', 'profile', 'payments', 'coupons', 'hide_cat_bar', 'point_logs', 'show_limit', 'rank'));
    }

    public function user_update(Request $request) {
        if (isset($request->id)) {
            
            $user = User::find($request->id);
            if ($user) {
                if (isset($request->point)) {
                    if ($user->point != $request->point) {
                        (new PointHistoryController)->create($user->id, $user->point, $request->point - $user->point, 'admin', 0);
                    }
                    $user->update(['point' => $request->point]);
                    return redirect()->back()->with('message', '保存しました！')->with('type', 'dialog');
                }
                if (isset($request->status)) {
                    $user->update(['status' => $request->status]);
                    $message = '承認しました。';
                    if ($request->status == 0) {
                        Product_log::where('user_id', $user->id)->where('status', 1)->update(['status' => 0]);
                        Product_log::where('user_id', $user->id)->where('status', 3)->update(['status' => 0]);
                        $message = 'ブロックしました。';
                    }
                    return redirect()->back()->with('message', $message)->with('type', 'dialog');
                }
            }
        }
    }

    public function user_add_point(Request $request) {
        if (isset($request->id)) {
            
            $user = User::find($request->id);
            if ($user) {
                if (isset($request->point)) {
                    if ($user->point != $request->point) {
                        (new PointHistoryController)->create($user->id, $user->point, $request->point, 'admin', 0);
                    }
                    $user->increment('point', $request->point);
                    return redirect()->back()->with('message', 'ポイントを付与しました。')->with('type', 'dialog');
                }
            }
        }
    }

    public function clear_history(Request $request) {
        $user = User::find($request->id);
        if ($user) {
            Point_history::where('user_id', $user->id)->delete();
            if ($user->point) (new PointHistoryController)->create($user->id, 0, $user->point, 'admin', 0);
            return redirect()->back()->with('message', '削除されました。')->with('type', 'dialog');
        }
    }

    public function videos() {
        $videos = Gacha_video::where('gacha_id', 0)->orderBy('level')->get();
        foreach ($videos as $video) {
            $video->file = getVideoUrl($video->file);
        }
        $hide_cat_bar = 1;
        return inertia('Admin/Videos', compact('videos', 'hide_cat_bar'));
    }

    public function video_store(Request $request) {
        $data = [];
        $data['level'] = $request->level;
        $data['point'] = $request->point ? $request->point : 0;
        $data['gacha_id'] = $request->gacha_id;
        if ($request->video) {
            $file = saveImage('videos/', $request->file('video'), false);
            $data['file'] = $file;
        }
        if ($request->id) {
            Gacha_video::where('id', $request->id)->update($data);
        }
        else {
            Gacha_video::create($data);
        }
        return redirect()->back()->with('message', '保存しました！')->with('title', '動画の設定')->with('type', 'dialog');
    }

    public function video_destroy($id) {
        $video = Gacha_video::find($id);
        if ($video) {
            Gacha_video::where('level', $video->level)->delete();
        }
        return redirect()->back()->with('message', '削除しました！')->with('title', '編集')->with('type', 'dialog');        
    }

    public function page_edit() {
        $titles = [
            'terms' => '利用規約',
            'privacy' => 'プライバシーポリシー',
            'notation' => '特定商取引法に基づく表記',
            
        ];
        $contents = [
            'terms' => getOption('terms'),
            'privacy' => getOption('privacy'),
            'notation' => getOption('notation'),
            
        ];
        $hide_cat_bar = 1;
        return inertia('Admin/Pages', compact('titles', 'contents', 'hide_cat_bar'));
    }

    public function page_update(Request $request) {
        if ($request->key) {
            setOption($request->key, $request->value ? $request->value : '');
        }
        return redirect()->back()->with('message', '保存しました！')->with('title', '動画の設定')->with('type', 'dialog');
    }

    public function bonus_edit() {
        $hide_cat_bar = 1;
        $bonus = Ranking_bonus::orderBy('rank')->get();

        return inertia('Admin/Rank/BonusSetting', compact('bonus', 'hide_cat_bar'));
    }

    public function bonus_update(Request $request) {
        $bonus = $request->bonus ?? [];
        Ranking_bonus::truncate();
        
        foreach($bonus as $item) {
            if (!isset($item['value'])) $item['value'] = '';
            Ranking_bonus::create($item);
        }

        return redirect()->back()->with('message', '保存しました！')->with('type', 'dialog');
    }

    public function ranking_history(Request $request) {
        $hide_cat_bar = 1;
        $begin = ($request->month ? Carbon::parse($request->month) : Carbon::now())->startOfMonth();
        $end = $begin->copy()->addMonth()->startOfMonth();

        $history = Ranking_history::where('endDate', '>=', $begin)->where('endDate', '<', $end)
        ->orderBy('endDate', 'desc')->orderBy('startDate', 'desc')->orderBy('rank')->get();

        foreach($history as $item) {
            $item->email = $item->user?->email;
            $item->startDate = Carbon::parse($item->startDate)->format('Y年n月j日');
            $item->endDate = Carbon::parse($item->endDate)->format('Y年n月j日');
        }
        return inertia('Admin/Rank/BonusHistory', compact('hide_cat_bar', 'history'));
    }

    public function get_ranking_history(Request $request) {
        $begin = ($request->month ? Carbon::parse($request->month) : Carbon::now())->startOfMonth();
        $end = $begin->copy()->addMonth()->startOfMonth();

        $history = Ranking_history::where('endDate', '>=', $begin)->where('endDate', '<', $end)
        ->orderBy('endDate', 'desc')->orderBy('startDate', 'desc')->orderBy('rank')->get();
        
        foreach($history as $item) {
            $item->email = $item->user->email;
            $item->startDate = Carbon::parse($item->startDate)->format('Y年n月j日');
            $item->endDate = Carbon::parse($item->endDate)->format('Y年n月j日');
        }
        return [
            'history' => $history
        ];
    }
}
