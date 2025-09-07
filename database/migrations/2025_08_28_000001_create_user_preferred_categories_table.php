<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Ne pas recréer la table si elle existe déjà
        if (!Schema::hasTable('user_preferred_categories')) {
            Schema::create('user_preferred_categories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('category_id');
                $table->timestamps();

                // Ajouter les clés étrangères uniquement si les tables référencées existent
                if (Schema::hasTable('users')) {
                    $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                }

                if (Schema::hasTable('categories')) {
                    $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
                }

                $table->unique(['user_id', 'category_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('user_preferred_categories');
    }
};
