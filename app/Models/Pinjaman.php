<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Pinjaman extends Model
{
    protected $casts = [
        'list_anggota' => 'json',
    ];
    
    use HasFactory;

    protected $table = 'pinjamans';

    public function mutasis(): HasOne
    {
        return $this->hasOne(Mutasi::class, 'pinjaman_id', 'id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'pinjaman_id', 'id');
    }

    public function list_anggota(): HasMany
    {
        return $this->hasMany(User::class, 'user_id', 'id');
    }

    public function cabangs(): BelongsTo
    {
        return $this->belongsTo(Cabang::class, 'cabang_id', 'id');
    }
}
