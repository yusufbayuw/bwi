<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cicilan extends Model
{
    use HasFactory;

    public function pinjamans(): BelongsTo
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_id', 'id');
    }

    public function mutasis(): HasOne
    {
        return $this->hasOne(Mutasi::class, 'mutasi_id', 'id');
    }

    public function cabangs(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'id');
    }
}
