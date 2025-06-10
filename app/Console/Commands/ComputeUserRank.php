<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Rank;
use Carbon\Carbon;

class ComputeUserRank extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compute:user-rank';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Compute user rank based on purchase amount and expiration date';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = Carbon::now()->toDateString();
        
        $users = User::where('month', '<', $today)->get();
        foreach ($users as $user) {
            $rank = Rank::where('rank', $user->current_rank)->first();
            if ($user->consume_point * 10 < $rank->limit * 3 && $user->current_rank > 1) {
                $user->current_rank --;
            }
            $user->consume_point = 0;
            $user->month = date('Y-m');
            $user->save();
        }
        return Command::SUCCESS;
    }
}
