<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Rank;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('ranks', function (Blueprint $table) {
            $table->integer('keep_limit')->nullable()->after('limit');
        });

        // Initialize keep_limit with limit * 30% for existing records
        $ranks = Rank::all();
        foreach ($ranks as $rank) {
            $rank->keep_limit = (int)($rank->limit * 0.3);
            $rank->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('ranks', function (Blueprint $table) {
            $table->dropColumn('keep_limit');
        });
    }
};
