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
        Schema::create('cabangs', function (Blueprint $table) {
            $table->id();
            $table->string('nama_cabang');
            $table->string('lokasi')->nullable();
            $table->string('ketua_pembina')->nullable();
            $table->json('anggota_pembina')->nullable();
            $table->string('ketua_pengawas')->nullable();
            $table->json('anggota_pengawas')->nullable();
            $table->string('ketua_pengurus')->nullable();
            $table->json('sekretaris')->nullable();
            $table->json('bendahara')->nullable();
            $table->string('saldo_awal')->nullable();
            $table->string('saldo_awal_prev')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabangs');
    }
};
