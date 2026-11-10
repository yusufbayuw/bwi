<?php

namespace App\Models;

use App\Models\Concerns\HasAtomicFinancialWrites;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Infak extends Model
{
    use HasAtomicFinancialWrites;

    use HasFactory;

    public function users(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function cabangs(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'id');
    }

    public function mutasis(): HasMany
    {
        return $this->hasMany(Mutasi::class, 'infak_id', 'id');
    }
}
