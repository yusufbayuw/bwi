<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mutasi extends Model
{
    use HasFactory;

    public function infaks(): BelongsTo
    {
        return $this->belongsTo(Infak::class, 'infak_id', 'id');
    }

    public function cabangs(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'id');
    }

    public function pengeluarans(): BelongsTo
    {
        return $this->belongsTo(Pengeluaran::class, 'pengeluaran_id', 'id');
    }

    public function pinjamans(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_id', 'id');
    }

    public function cicilans(): BelongsTo
    {
        return $this->belongsTo(Cicilan::class, 'cicilan_id', 'id');
    }
}
