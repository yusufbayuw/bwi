<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabang extends Model
{
    protected $casts = [
        'anggota_pembina' => 'json',
        'anggota_pengawas' => 'json',
    ];

    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_id', 'id');
    }

    public function infaks(): HasMany
    {
        return $this->hasMany(Infak::class);
    }

    public function cicilans(): HasMany
    {
        return $this->hasMany(Cicilan::class);
    }

    public function pengeluarans(): HasMany
    {
        return $this->hasMany(Pengeluaran::class);
    }

    public function pinjamans(): HasMany
    {
        return $this->hasMany(Pinjaman::class);
    }

    public function mutasis(): HasMany
    {
        return $this->hasMany(Mutasi::class);
    }

}
