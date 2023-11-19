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
        Schema::create('pinjamans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->cascadeOnDelete();
            $table->string('nama_kelompok');
            $table->integer('jumlah_anggota')->nullable();
            $table->json('list_anggota')->nullable();
            $table->string('berkas')->nullable();
            $table->string('nominal_bmpa_max')->nullable();
            $table->integer('lama_cicilan')->nullable();
            $table->string('status')->nullable();
            $table->string('total_pinjaman')->nullable();
            $table->boolean('acc_pinjaman')->default(false);
            $table->date('tanggal_cicilan_pertama')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pinjamans');
    }
};
