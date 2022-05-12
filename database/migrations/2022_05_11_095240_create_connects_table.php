<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateConnectsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('connects', function (Blueprint $table) {
            $table->id();
            $table->string('message_last');
            $table->date('send_last');
            $table->integer('count_message_day')->default(50);
            $table->foreignId('from_user_id')->constrained('users');
            $table->integer('to_user_id')->nullable();
            $table->string('status')->default('FREE');
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
        Schema::dropIfExists('connects');
    }
}
