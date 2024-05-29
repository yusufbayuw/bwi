<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Cabang extends Model
{
    protected $casts = [
        'anggota_pembina' => 'json',
        'anggota_pengawas' => 'json',
        'sekretaris' => 'json',
        'bendahara' => 'json',
    ];

    use HasFactory;

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'cabang_id', 'id');
    }

    public function infaks(): HasMany
    {
        return $this->hasMany(Infak::class, 'cabang_id', 'id');
    }

    public function cicilans(): HasMany
    {
        return $this->hasMany(Cicilan::class, 'cabang_id', 'id');
    }

    public function pengeluarans(): HasMany
    {
        return $this->hasMany(Pengeluaran::class, 'cabang_id', 'id');
    }

    public function pinjamans(): HasMany
    {
        return $this->hasMany(Pinjaman::class, 'cabang_id', 'id');
    }

    public function mutasis(): HasMany
    {
        return $this->hasMany(Mutasi::class, 'cabang_id', 'id');
    }

    public function laporans(): HasMany
    {
        return $this->hasMany(Laporan::class, 'cabang_id', 'id');
    }
}
