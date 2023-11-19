<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Pengeluaran extends Model
{
    use HasFactory;

    public function cabangs(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function mutasis(): HasOne
    {
        return $this->hasOne(Mutasi::class);
    }
}
