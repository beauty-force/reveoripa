<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('ranking_history', function (Blueprint $table) {
            $table->id();
            $table->string('period');
            $table->datetime('startDate');
            $table->datetime('endDate');
            $table->integer('rank');
            $table->bigInteger('user_id');
            $table->bigInteger('ref_id');
            $table->bigInteger('total_point');
            $table->text('description')->nullable();
            $table->tinyInteger('status')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('ranking_history');
    }
};
