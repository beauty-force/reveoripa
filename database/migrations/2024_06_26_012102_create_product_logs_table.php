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
        Schema::create('product_logs', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->string('name');
            $table->string('rare')->nullable();
            $table->integer('point')->default(0);
            $table->bigInteger('product_id');
            $table->bigInteger('user_id');
            $table->bigInteger('gacha_record_id');
            $table->tinyInteger('status');
            $table->string('tracking_number')->nullable();
            $table->integer('rank')->default(0);
            $table->string('gacha_title')->nullable();
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
        Schema::dropIfExists('product_logs');
    }
};
