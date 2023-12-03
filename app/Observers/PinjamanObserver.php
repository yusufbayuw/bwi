<?php

namespace App\Observers;

use App\Models\Mutasi;
use App\Models\Pinjaman;

class PinjamanObserver
{
    /**
     * Handle the Pinjaman "created" event.
     */
    public function created(Pinjaman $pinjaman): void
    {
        //
        $totalPinjaman = (float)$pinjaman->total_pinjaman;

        if ($totalPinjaman) {
            $pinjaman_id = $pinjaman->id;
            $cabang_id = $pinjaman->cabang_id;

            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
            $last_mutasi_umum = (float)($last_mutasi->saldo_umum ?? 0);
            $last_mutasi_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
            $last_mutasi_csr = (float)($last_mutasi->saldo_csr ?? 0);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'pinjaman_id' => $pinjaman_id,
                'debet' => $totalPinjaman,
                'saldo_umum' => $last_mutasi_umum - $totalPinjaman,
                'saldo_keamilan' => $last_mutasi_keamilan,
                'saldo_csr' => $last_mutasi_csr,
                'keterangan' => "Pinjaman kelompok ".$pinjaman->nama_kelompok,
            ]);

            $jumlahAnggota = $pinjaman->jumlah_anggota;

            dd($pinjaman->list_anggota);
            for ($i=1; $i <= $jumlahAnggota ; $i++) { 
                #
            }

        }
    }

    /**
     * Handle the Pinjaman "updated" event.
     */
    public function updated(Pinjaman $pinjaman): void
    {
        //
    }

    /**
     * Handle the Pinjaman "deleted" event.
     */
    public function deleted(Pinjaman $pinjaman): void
    {
        //
    }

    /**
     * Handle the Pinjaman "restored" event.
     */
    public function restored(Pinjaman $pinjaman): void
    {
        //
    }

    /**
     * Handle the Pinjaman "force deleted" event.
     */
    public function forceDeleted(Pinjaman $pinjaman): void
    {
        //
    }
}
