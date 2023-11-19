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
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->foreignId('pinjaman_id')->nullable()->constrained('pinjamans')->nullOnDelete();
            $table->foreignId('cicilan_id')->nullable()->constrained('cicilans')->nullOnDelete();
            $table->foreignId('pengeluaran_id')->nullable()->constrained('pengeluarans')->nullOnDelete();
            $table->foreignId('infak_id')->nullable()->constrained('infaks')->nullOnDelete();
            $table->string('debet')->nullable();
            $table->string('kredit')->nullable();
            $table->string('saldo_umum')->nullable();
            $table->string('saldo_keamilan')->nullable();
            $table->string('saldo_csr')->nullable();
            $table->date('tanggal')->nullable();
            $table->string('keterangan');
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
