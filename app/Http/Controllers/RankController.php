<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Rank;
use App\Models\Ranking_bonus;
use Str;
use DB;
use Carbon\Carbon;

class RankController extends Controller
{
    public function index()
    {
        $ranks = Rank::orderby('rank', 'desc')->get();
        foreach ($ranks as $rank) {
            $rank->image = getRankImageUrl($rank->image);
            $rank->badge = getRankImageUrl($rank->badge);
        }
        $hide_cat_bar = 1;
        return inertia('Admin/Rank/Index', compact('hide_cat_bar', 'ranks'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    

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
            'rank' => 'required|numeric',
            'title' => 'required|string',
            'limit' => 'required|numeric',
            'keep_limit' => 'nullable|numeric',
            'bonus' => 'required|numeric',
            'pt_rate' => 'required|numeric',
            'dp_rate' => 'required|numeric',
        ]);

        $data = [
            'rank' => $request->rank,
            'title' => $request->title,
            'limit' => $request->limit,
            'keep_limit' => $request->keep_limit,
            'bonus' => $request->bonus,
            'pt_rate' => $request->pt_rate,
            'dp_rate' => $request->dp_rate,
        ];
        if($request->image){
            $image = saveImage('images/ranks', $request->file('image'), false);
            $data['image'] = $image;
        }
        if($request->badge){
            $image = saveImage('images/ranks', $request->file('badge'), false);
            $data['badge'] = $image;
        }

        Rank::updateOrCreate(
            ['id' => $id],
            $data
        );

        return redirect()->back()->with('message', '保存しました！')->with('title', 'ランク')->with('type', 'dialog');
    }

    public function destroy($id)
    {
        Rank::where('id', $id)->delete();
        return redirect()->back()->with('message', '削除しました！')->with('title', 'ポイント配布')->with('type', 'dialog');
    }

    public function ranking() {
        $hide_cat_bar = 1;
        return inertia('Normal/Ranking', compact('hide_cat_bar'));
    }

    public function ranking_about() {
        $hide_cat_bar = 1;
        $today = Carbon::today();
        $bonus = Ranking_bonus::orderBy('rank')->get();
        return inertia('Normal/RankingAbout', compact('hide_cat_bar', 'bonus'));
    }

    public function ranking_data(Request $request) {
        $period = $request->period;
        if ($period == 'total') {
            $length = 30;
        } else {
            $bonus = Ranking_bonus::where('period', $period)->orderBy('rank')->get();
            if (count($bonus) == 0) return [
                'users' => []
            ];

            $last_rank = $bonus[count($bonus) - 1];
            $length = $last_rank->value == 0 ? $last_rank->rank - 1 : $last_rank->rank;
        }

        $startDate = match($period) {
            'daily' => Carbon::today(),
            'weekly' => Carbon::now()->startOfWeek(),
            'monthly' => Carbon::now()->startOfMonth(),
            default => null, // 'total' or any invalid period returns all-time data
        };
        $subQuery = DB::table('point_history')
            ->select('user_id', DB::raw('SUM(-point_diff) as total_points'))
            ->where('point_type', 'gacha') // Filter only 'gacha' points
            ->when($startDate, function ($query) use ($startDate) {
                return $query->where('created_at', '>=', $startDate);
            })
            ->groupBy('user_id');

        $tenthHighestPoints = DB::table(DB::raw("({$subQuery->toSql()}) as points_sub"))
            ->mergeBindings($subQuery)
            ->orderByDesc('total_points')
            ->skip($length - 1) // Skip the first 9 to find the 10th highest
            ->value('total_points');

        // If fewer than 10 users or no points, set $tenthHighestPoints to 0
        $tenthHighestPoints = $tenthHighestPoints ?? 1;

        // Now, get all users with total_points equal to or greater than $tenthHighestPoints
        $query = DB::table(DB::raw("({$subQuery->toSql()}) as points_sub"))
        ->mergeBindings($subQuery)
        ->join('users', 'points_sub.user_id', '=', 'users.id')
        ->select('users.id', 'users.name', 'users.avatar', 'points_sub.total_points')
        ->where('points_sub.total_points', '>=', $tenthHighestPoints)
        ->orderByDesc('points_sub.total_points');

        return [
            'users' => $query->get()
        ];
    }

}
