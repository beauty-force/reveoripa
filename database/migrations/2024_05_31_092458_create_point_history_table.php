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
        Schema::create('point_history', function (Blueprint $table) {
            $table->id();
            $table->integer('point_before');
            $table->integer('point_diff');
            $table->string('point_type');
            $table->bigInteger('user_id');
            $table->bigInteger('ref_id');
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
        Schema::dropIfExists('point_history');
    }
};
