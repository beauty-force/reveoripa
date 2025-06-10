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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('plan_id')->unique(); // Fincode plan ID
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('amount');
            $table->integer('point');
            $table->timestamps();
        });

        // Create user_subscriptions table to track user subscriptions
        Schema::create('user_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->string('subscription_id')->unique(); // Fincode subscription ID
            $table->string('card_id')->nullable();
            $table->datetime('start_date');
            $table->datetime('stop_date')->nullable();
            $table->string('status'); // active, cancelled, expired
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
        Schema::dropIfExists('user_subscriptions');
        Schema::dropIfExists('plans');
    }
};
