<?php

namespace App\Observers;

use App\Models\Mutasi;
use App\Models\Pengeluaran;

class PengeluaranObserver
{
    /**
     * Handle the Pengeluaran "created" event.
     */
    public function created(Pengeluaran $pengeluaran): void
    {
        $pengeluaran_nominal = (float)$pengeluaran->nominal;

        if ($pengeluaran_nominal) {
            $pengeluaran_id = $pengeluaran->id;
            $cabang_id = $pengeluaran->cabang_id;
            $jenis = $pengeluaran->jenis;

            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
            $last_mutasi_umum = (float)($last_mutasi->saldo_umum ?? 0);
            $last_mutasi_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
            $last_mutasi_csr = (float)($last_mutasi->saldo_csr ?? 0);

            $mutasi_2 = ($jenis === "Umum") ? $last_mutasi_umum - $pengeluaran_nominal : $last_mutasi_umum;
            $mutasi_4 = ($jenis === "Keamilan") ? $last_mutasi_keamilan - $pengeluaran_nominal : $last_mutasi_keamilan;
            $mutasi_6 = ($jenis === "CSR") ? $last_mutasi_csr - $pengeluaran_nominal : $last_mutasi_csr;

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'pengeluaran_id' => $pengeluaran_id,
                'debet' => $pengeluaran_nominal,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_6,
                'keterangan' => "Pengeluaran ".$jenis.": ".$pengeluaran->keterangan,
            ]);
        }
    }

    /**
     * Handle the Pengeluaran "updated" event.
     */
    public function updated(Pengeluaran $pengeluaran): void
    {
        $pengeluaran_nominal = (float)$pengeluaran->nominal;
        $pengeluaran_nominal_lama = (float)$pengeluaran->getOriginal('nominal');

        if ($pengeluaran_nominal && ($pengeluaran_nominal != $pengeluaran_nominal_lama)) {
            $pengeluaran_id = $pengeluaran->getOriginal('id');
            $cabang_id = $pengeluaran->cabang_id;
            $jenis = $pengeluaran->jenis;

            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
            $last_mutasi_umum = (float)($last_mutasi->saldo_umum ?? 0);
            $last_mutasi_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
            $last_mutasi_csr = (float)($last_mutasi->saldo_csr ?? 0);

            $mutasi_1 = ($jenis === "Umum") ? $last_mutasi_umum + $pengeluaran_nominal_lama : $last_mutasi_umum;
            $mutasi_2 = ($jenis === "Umum") ? $mutasi_1 - $pengeluaran_nominal : $mutasi_1;
            $mutasi_3 = ($jenis === "Keamilan") ? $last_mutasi_keamilan + $pengeluaran_nominal_lama : $last_mutasi_keamilan;
            $mutasi_4 = ($jenis === "Keamilan") ? $mutasi_3 - $pengeluaran_nominal : $mutasi_3;
            $mutasi_5 = ($jenis === "CSR") ? $last_mutasi_csr + $pengeluaran_nominal_lama : $last_mutasi_csr;
            $mutasi_6 = ($jenis === "CSR") ? $mutasi_5 - $pengeluaran_nominal : $mutasi_5;

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'pengeluaran_id' => $pengeluaran_id,
                'kredit' => $pengeluaran_nominal_lama,
                'saldo_umum' => $mutasi_1,
                'saldo_keamilan' => $mutasi_3,
                'saldo_csr' => $mutasi_5,
                'keterangan' => "Perubahan Pengeluaran (lama) ".$jenis.": ".$pengeluaran->keterangan,
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'pengeluaran_id' => $pengeluaran_id,
                'debet' => $pengeluaran_nominal,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_6,
                'keterangan' => "Perubahan Pengeluaran (baru) ".$jenis.": ".$pengeluaran->keterangan,
            ]);
        }
    }

    /**
     * Handle the Pengeluaran "deleted" event.
     */
    public function deleted(Pengeluaran $pengeluaran): void
    {
        //
    }

    /**
     * Handle the Pengeluaran "restored" event.
     */
    public function restored(Pengeluaran $pengeluaran): void
    {
        //
    }

    /**
     * Handle the Pengeluaran "force deleted" event.
     */
    public function forceDeleted(Pengeluaran $pengeluaran): void
    {
        //
    }
}
