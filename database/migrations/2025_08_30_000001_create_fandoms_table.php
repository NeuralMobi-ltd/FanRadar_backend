<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Ne pas recréer la table si elle existe déjà
        if (!Schema::hasTable('fandoms')) {
            Schema::create('fandoms', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                // rendre nullable pour éviter les erreurs si subcategories n'existe pas encore
                $table->unsignedBigInteger('subcategory_id')->nullable();
                $table->timestamps();

                // Ajouter la contrainte étrangère seulement si la table subcategories existe
                if (Schema::hasTable('subcategories')) {
                    $table->foreign('subcategory_id')->references('id')->on('subcategories')->onDelete('cascade');
                }
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('fandoms');
    }
};
