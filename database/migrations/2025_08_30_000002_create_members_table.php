<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('fandom_id');
            $table->enum('role', ['member', 'moderator', 'admin'])->default('member');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('fandom_id')->references('id')->on('fandoms')->onDelete('cascade');
            $table->unique(['user_id', 'fandom_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('members');
    }
};
