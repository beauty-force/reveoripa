<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;

use App\Models\Gacha;
use App\Http\Resources\GachaListResource;
use App\Http\Resources\ProductListResource;
use App\Models\Gacha_record;
use App\Models\Point_history;
use Illuminate\Support\Facades\Cache;

use App\Models\Product;
use Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HomeController extends Controller
{
    public function get_period_day($current) {
        $current_day = date('Y-m-d 20:00:00', strtotime($current));
        if ($current < $current_day) $current = date('Y-m-d', strtotime($current.' -1 days'));
        else $current = date('Y-m-d', strtotime($current));
        return $current;
    }

    public function index(Request $request) {
        $cat_id = $request->cat_id ? $request->cat_id : getCategories()[0]->id;
        $user = auth()->user();
        $rank = $user && $user->type == 0 ? $user->current_rank : 0;

        $gachas = Gacha::where('category_id', $cat_id)
            ->where('status', 1)
            ->whereRaw('(ISNULL(end_time) || end_time>NOW())');

        if (!$user || $user->getType() == 'user') {
            $gachas = $gachas->where(function($query) use ($rank) {
                $query->where('rank_limit', '<=', $rank);
                    // ->where('rank_limit', '=', $rank)
                    // ->orWhere('rank_limit', 0);
            });
        }
        
        $gachas = $gachas->orderBy('order_level', 'DESC')->orderBy('id', 'DESC')->get();
            // ->orderBy('order_level', 'DESC')->orderBy('id', 'DESC')->get();
        
        if ($user && $user->type == 0) {
            $gachas_ = [];
            $pulled = [];
            foreach($gachas as $gacha) {
                $gacha->status = 0;
                if ($gacha->gacha_limit == 1) {
                    $last = Gacha_record::where('user_id', $user->id)->where('gacha_id', $gacha->id)->where('status', '!=', 0)->latest()->first();
                    if ($last) {
                        $gacha->status = 1;
                        $now = $this->get_period_day(date('Y-m-d H:i:s'));
                        $record = $this->get_period_day($last->updated_at);
                        if ($now == $record) {
                            array_push($pulled, $gacha);
                            continue;
                        }
                    }
                }
                array_push($gachas_, $gacha);
            }
            $gachas = array_merge($gachas_, $pulled);
        }
        $gachas = GachaListResource::collection($gachas);
        $hide_back_btn = 1;
        $branch_is_gacha = 1;
        $show_notification = 0;
        $banners = DB::table('banner')->orderBy('order')->get()->toArray();
        // $last24HourConsumePoints = Cache::remember('last24HourConsumePoints', 10, function () {
        //     return Point_history::where('point_type', 'gacha')
        //         ->where('created_at', '>=', now()->subDays(7))
        //         ->sum(DB::raw('-point_diff'));
        // });
        // $last24HourConsumePoints = Point_history::where('point_type', 'gacha')
        //     ->where('created_at', '>=', now()->subDays(7))
        //     ->sum(DB::raw('-point_diff'));

        return inertia('Home', compact('gachas', 'hide_back_btn', 'branch_is_gacha', 'show_notification', 'banners'));   
    }

    public function dp(Request $request) {
        $cat_id = $request->cat_id ? $request->cat_id : getCategories()[0]->id;
        
        $products = Product::where('is_lost_product', 2)->where('category_id', $cat_id)->orderBy(DB::raw('marks>0'), 'desc')->orderBy('dp', 'desc')->get();
        $products = ProductListResource::collection($products);

        $hide_back_btn = 1; 
        $branch_is_gacha = 2;
        $banners = DB::table('banner')->orderBy('order')->get()->toArray();

        return inertia('HomeDp', compact('products', 'hide_back_btn', 'branch_is_gacha', 'banners'));
    }

    public function dashboard() {
        if(Auth::check()) {
            if (auth()->user()->getType() == 'admin') {
                // return redirect()->route('admin');
                return redirect()->route('admin.gacha');
            }else{
                return redirect()->route('main');
            }
        }
        return redirect()->route('main');
    }

    public function how_to_use() {
        $hide_cat_bar = 1;
        return inertia('Normal/HowToUse', compact('hide_cat_bar'));
    }

    public function privacy_police() {
        $hide_cat_bar = 1;
        $title = 'プライバシーポリシー';
        $content = getOption('privacy');
        return inertia('Normal/Page', compact('hide_cat_bar', 'title', 'content'));
    }

    public function terms_conditions() {
        $hide_cat_bar = 1;
        $title = '利用規約';
        $content = getOption('terms');
        return inertia('Normal/Page', compact('hide_cat_bar', 'title', 'content'));
    }

    public function partner_store() {
        $hide_cat_bar = 1;
        $title = '提携店舗様';
        $content = getOption('partner_store');
        return inertia('Normal/Page', compact('hide_cat_bar', 'title', 'content'));
    }

    public function partner_site() {
        $hide_cat_bar = 1;
        $title = '提携サイト様';
        $content = getOption('partner_site');
        return inertia('Normal/Page', compact('hide_cat_bar', 'title', 'content'));
    }
    
    public function contact_us() {
        $hide_cat_bar = 1;
        return inertia('Normal/ContactUs', compact('hide_cat_bar'));
    }

    public function notation_commercial() {
        $hide_cat_bar = 1;
        $title = '特定商取引法に基づく表記';
        $content = getOption('notation');
        return inertia('Normal/Page', compact('hide_cat_bar', 'title', 'content'));
    }

    public function maintainance() {
        $maintainance = getOption('maintainance');
        if ($maintainance!="1") {
            return redirect()->route('main');
        }
        return inertia('Maintainance');
    }

    public function dp_table() {
        $hide_cat_bar = 1;
        $title = 'DP交換表';
        $content = getOption('dp_table');
        return inertia('User/Dp/Table', compact('hide_cat_bar', 'title', 'content'));
    }
}
