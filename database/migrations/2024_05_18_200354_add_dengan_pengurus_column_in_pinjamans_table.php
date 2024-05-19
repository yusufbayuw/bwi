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
        Schema::table('pinjamans', function (Blueprint $table) {
            $table->boolean('dengan_pengurus')->default(false)->nullable()->after('nama_kelompok');
            $table->string('nama_pengurus')->nullable()->after('dengan_pengurus');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pinjamans', function (Blueprint $table) {
            $table->dropColumn('dengan_pengurus');
            $table->dropColumn('nama_pengurus');
        });
    }
};
