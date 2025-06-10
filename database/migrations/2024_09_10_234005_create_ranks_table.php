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
        Schema::create('ranks', function (Blueprint $table) {
            $table->id();
            $table->integer('rank');
            $table->string('title');
            $table->string('image')->nullable();
            $table->string('badge');
            $table->integer('limit');
            $table->integer('bonus');
            $table->float('pt_rate')->precision(2);
            $table->float('dp_rate')->precision(2);
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
        Schema::dropIfExists('ranks');
    }
};
