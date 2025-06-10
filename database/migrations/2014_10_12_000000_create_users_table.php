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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            
            $table->rememberToken();
            $table->tinyInteger('type')->default(0);
            /* Users: 0=>User, 1=>Admin, 2=>Manager */

            $table->integer('dp')->default(0);
            $table->integer('point')->default(0);
            $table->integer('current_plan')->default(0);
            $table->string('customer_id')->nullable();
            $table->string('line_id')->nullable();
            $table->string('avatar')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->integer('current_rank')->default(1);
            $table->integer('consume_point')->default(0);
            $table->string('month', length: 10)->nullable();
            $table->string('invite_code')->nullable();

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
        Schema::dropIfExists('users');
    }
};
