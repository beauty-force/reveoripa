<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Coupon_record;
use App\Models\Point;
use App\Models\CouponDiscountRate;
use DB;

use Illuminate\Http\Request;
use Str;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $coupons = Coupon::leftJoin(DB::raw('(select coupon_id, sum(status) as used_count from coupon_records GROUP BY coupon_id) A'),
            function($join) {
                $join->on('coupons.id', '=', 'A.coupon_id');
            },
            DB::raw('A')
        )
        ->select('coupons.*', 'A.used_count')
        ->orderBy(DB::raw('CASE WHEN coupons.count - A.used_count > 0 THEN 0 ELSE 1 END'))
        ->orderBy('coupons.id')
        ->get();
        $types = [
            'NORMAL' => '普通',
            'DISCOUNT' => '割引'
        ];
        $hide_cat_bar = 1;
        foreach($coupons as $coupon) {
            if ($coupon->type == 'DISCOUNT') {
                $rates = $coupon->discount_rate->toArray();
                $coupon['min_rate'] = min(array_column($rates, 'rate'));
                $coupon['max_rate'] = max(array_column($rates, 'rate'));
            }
        }
        return inertia('Admin/Coupon/Index', compact('coupons', 'hide_cat_bar', 'types'));
    }

    public function create_card_number($length = 10)
    {
        $string = 'REVE-'.Str::random($length);
        return $string;
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {
        if ($request->id) {
            $coupon = Coupon::where('id', $request->id)->first();
            $coupon = [
                'id' => '',
                'title' => $coupon->title,
                'type' => $coupon->type,
                'code' => $this->create_card_number(),
                'point' => $coupon->point,
                'discount_rate' => $coupon->discount_rate,
                'expiration' => $coupon->expiration,
                'user_limit' => $coupon->user_limit,
                'count' => $coupon->count,
            ];
        }
        else {
            $coupon = [
                'id' => '',
                'title' => '',
                'type' => 'NORMAL',
                'code' => $this->create_card_number(),
                'point' => '',
                'discount_rate' => [],
                'expiration' => date('Y-m-d H:i'),
                'user_limit' => 1,
                'count' => 1,
                // 'need_line' => 0,
            ];
        }
        $types = [
            'NORMAL' => '普通',
            'DISCOUNT' => '割引'
        ];
        $points = Point::select('point')->orderby('point')->get();
        $hide_cat_bar = 1;
        return inertia('Admin/Coupon/New', compact('hide_cat_bar', 'coupon', 'types', 'points'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $id = $request->id;
        $request->validate([
            'title' => 'required',
            'type' => 'required',
            'code' => 'required',
            'expiration' => 'required',
            'count' => 'required',
            'user_limit' => 'required',
            // 'need_line' => 'required'
        ]);

        if ($request->type == 'NORMAL') {
            $request->validate([
                'point' => 'required|integer',
            ]);
        }
        else if ($request->type == 'DISCOUNT') {
            $count = Point::count();
            $request->validate([
                'discount_rate' => 'required|array|size:'.$count,
                'discount_rate.*' => 'required|integer|gt:-1|lt:101',
            ]);
        }

        $title = 'ポイント配布編集';
        $coupon = Coupon::updateOrCreate(
            [
                'id' => $id
            ],
            [
                'title' => $request->title,
                'type' => $request->type,
                'point' => $request->type == 'NORMAL' ? $request->point : 0,
                'code' => $request->code,
                'expiration' => date('Y-m-d H:i', strtotime($request->expiration)),
                'count' => $request->count,
                'user_limit' => $request->user_limit,
                // 'need_line' => $request->need_line
            ]
        );
        if ($coupon->type=='DISCOUNT') {
            $coupon->discount_rate = $request->discount_rate;
            $points = Point::orderby('point')->get();
            for($i = 0; $i < count($points); $i++) {
                CouponDiscountRate::updateOrCreate([
                    'coupon_id' => $coupon->id,
                    'point_id' => $points[$i]->id
                ],
                [
                    'rate' => $request->discount_rate[$i]
                ]);
            }
        }
        if ($id == '') {
            $coupon = [
                'title' => '',
                'type' => 'NORMAL',
                'code' => $this->create_card_number(),
                'point' => '',
                'expiration' => date('Y-m-d H:i'),
                'count' => 1,
                'user_limit' => 1,
                'discount_rate' => [],
                // 'need_line' => 0
            ];
            $title = 'ポイント配布追加';
        }
        return redirect()->back()->with('message', '保存しました！')->with('title', $title)->with('type', 'dialog')->with('data', $coupon);
    }

    public function edit($id)
    {
        $coupon = Coupon::where(['id' => $id])->first();
        $editing = true;
        $hide_cat_bar = 1;
        $types = [
            'NORMAL' => '普通',
            'DISCOUNT' => '割引'
        ];
        
        $coupon['discount_rate']=$coupon->discount_rate;
        $points = Point::select('id', 'point')->orderby('point')->get();
        return inertia('Admin/Coupon/New', compact('coupon', 'editing', 'hide_cat_bar', 'types', 'points'));
    }

    public function delete($id)
    {
        $coupon = Coupon::where('id', $id)->delete();
        Coupon_record::where('coupon_id', $id)->delete();
        return redirect()->back()->with('message', '削除しました！')->with('title', 'ポイント配布')->with('type', 'dialog');
    }
}
