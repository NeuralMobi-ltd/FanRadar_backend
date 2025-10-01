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
        Schema::table('fandoms', function (Blueprint $table) {
            $table->boolean('isactive')->default(true)->after('logo_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fandoms', function (Blueprint $table) {
            $table->dropColumn('isactive');
        });
    }
};
