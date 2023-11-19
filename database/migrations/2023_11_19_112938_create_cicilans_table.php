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
        Schema::create('cicilans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->cascadeOnDelete();
            $table->foreignId('pinjaman_id')->nullable()->constrained('pinjamans')->cascadeOnDelete();
            $table->string('nominal_cicilan')->nullable();
            $table->date('tanggal_cicilan')->nullable();
            $table->string('tagihan_ke')->nullable();
            $table->boolean('is_final')->default(false);
            $table->string('berkas')->nullable();
            $table->boolean('status_cicilan')->default(false);
            $table->date('tanggal_bayar')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cicilans');
    }
};
