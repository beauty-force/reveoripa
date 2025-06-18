<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Point_history;
use App\Http\Controllers\PointHistoryController;
use DB;
use Carbon\Carbon;

class CheckUserPointValidation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'validate:user-point';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check user point validation';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // get last point_history where point_type is gacha or purchase per user
        $point_histories = Point_history::where('point_type', 'gacha')->orWhere('point_type', 'purchase')
            ->select('user_id', DB::raw('MAX(updated_at) as last_updated_at'))
            ->groupBy('user_id')
            ->orderBy('last_updated_at')
            ->get();

        // remove point from users whose last_updated_at is older than 180 days
        foreach ($point_histories as $point_history) {
            if (Carbon::parse($point_history->last_updated_at)->diffInDays(Carbon::now()) > 180) {
                $user = User::find($point_history->user_id);
                if ($user && $user->point > 0) {
                    (new PointHistoryController)->create($user->id, $user->point, -$user->point, 'reset', 0);
                    $user->point = 0;
                    $user->save();
                }
            }
            else break;
        }

        return Command::SUCCESS;
    }
}
