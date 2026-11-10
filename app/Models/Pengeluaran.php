<?php

namespace App\Models;

use App\Models\Concerns\HasAtomicFinancialWrites;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pengeluaran extends Model
{
    use HasAtomicFinancialWrites;

    use HasFactory;

    public function cabangs(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'id');
    }

    public function mutasis(): HasOne
    {
        return $this->hasOne(Mutasi::class, 'pengeluaran_id', 'id');
    }
}
