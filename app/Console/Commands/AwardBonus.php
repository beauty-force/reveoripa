<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Ranking_bonus;
use App\Models\Ranking_history;
use App\Http\Controllers\PointHistoryController;
use DB;
use Carbon\Carbon;

class AwardBonus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'award:bonus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Award bonuses to users based on daily, weekly, and monthly conditions.';

    public function __construct() {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Implement the award logic here
        $today = Carbon::today();
        
        // Daily Bonus
        $this->awardDailyBonus();
        
        // Weekly Bonus
        if ($today->isMonday()) {
            $this->awardWeeklyBonus();
        }
        
        // Monthly Bonus
        if ($today->isSameDay($today->copy()->firstOfMonth())) {
            $this->awardMonthlyBonus();
        }
        
        $this->info('Bonuses awarded successfully.');
        return Command::SUCCESS;
    }

    protected function awardDailyBonus()
    {
        $yesterday = Carbon::yesterday();
        $this->awardBonusForPeriod('daily', $yesterday, Carbon::today());
    }

    protected function awardWeeklyBonus()
    {
        $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
        $lastWeekEnd = Carbon::now()->startOfWeek();
        $this->awardBonusForPeriod('weekly', $lastWeekStart, $lastWeekEnd);
    }

    protected function awardMonthlyBonus()
    {
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->startOfMonth();
        $this->awardBonusForPeriod('monthly', $lastMonthStart, $lastMonthEnd);
    }

    protected function awardBonusForPeriod($period, $startDate, $endDate)
    {
        $bonus = Ranking_bonus::where('period', $period)->orderBy('rank')->get();
        if (count($bonus) == 0) return ;

        $last_rank = $bonus[count($bonus) - 1];
        $length = $last_rank->value == 0 ? $last_rank->rank - 1 : $last_rank->rank;
        
        $subQuery = DB::table('point_history')
            ->select('user_id', DB::raw('SUM(-point_diff) as total_points'))
            ->where('point_type', 'gacha') // Filter only 'gacha' points
            ->where('created_at', '>=', $startDate)
            ->where('created_at', '<', $endDate)
            ->groupBy('user_id');

        $tenthHighestPoints = DB::table(DB::raw("({$subQuery->toSql()}) as points_sub"))
            ->mergeBindings($subQuery)
            ->orderByDesc('total_points')
            ->skip($length - 1)
            ->value('total_points');

        $tenthHighestPoints = $tenthHighestPoints ?? 1;

        // Now, get all users with total_points equal to or greater than $tenthHighestPoints
        $users = DB::table(DB::raw("({$subQuery->toSql()}) as points_sub"))
        ->mergeBindings($subQuery)
        ->join('users', 'points_sub.user_id', '=', 'users.id')
        ->select('users.id', 'points_sub.total_points')
        ->where('points_sub.total_points', '>=', $tenthHighestPoints)
        ->orderByDesc('points_sub.total_points')->get();
        
        $id = 0;
        $rank = 0;
        foreach($users as $index => $user) {
            if ($index > 0 && $users[$index - 1]->total_points != $user->total_points) $rank = $index;
            if ($id + 1 < count($bonus) && $bonus[$id + 1]->rank <= $rank + 1) $id ++;
            
            $_user = User::find($user->id);
            $ref_id = 0;

            $ref_id = (new PointHistoryController)->create($_user->id, $_user->point, intval($bonus[$id]->value), $period.'_bonus', $rank + 1);
            $_user->increment('point', intval($bonus[$id]->value));
            $log = Ranking_history::create([
                'period' => $period,
                'startDate' => $startDate,
                'endDate' => $endDate->copy()->subSecond(),
                'rank' => $rank + 1,
                'user_id' => $_user->id,
                'ref_id' => $ref_id,
                'total_point' => $user->total_points,
                'description' => $bonus[$id]->value
            ]);
        }
        echo "Awarding $period bonus for period: " . $startDate->toDateString() . " to " . $endDate->toDateString() . "\n";
    }
}
