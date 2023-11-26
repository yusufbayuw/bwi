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
        Schema::create('mutasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->cascadeOnDelete();
            $table->foreignId('pinjaman_id')->nullable()->constrained('pinjamans')->cascadeOnDelete();
            $table->foreignId('cicilan_id')->nullable()->constrained('cicilans')->cascadeOnDelete();
            $table->foreignId('pengeluaran_id')->nullable()->constrained('pengeluarans')->cascadeOnDelete();
            $table->foreignId('infak_id')->nullable()->constrained('infaks')->cascadeOnDelete();
            $table->string('debet')->nullable();
            $table->string('kredit')->nullable();
            $table->string('saldo_umum')->nullable();
            $table->string('saldo_keamilan')->nullable();
            $table->string('saldo_csr')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mutasis');
    }
};
