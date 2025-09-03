<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // ajouter fandom_id nullable et contrainte FK vers fandoms
            $table->foreignId('fandom_id')->nullable()->constrained('fandoms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // supprimer la contrainte puis la colonne
            if (Schema::hasColumn('posts', 'fandom_id')) {
                $table->dropForeign(['fandom_id']);
                $table->dropColumn('fandom_id');
            }
        });
    }
};
