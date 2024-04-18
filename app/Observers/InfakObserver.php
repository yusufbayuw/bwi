<?php

namespace App\Observers;

use App\Models\Infak;
use App\Models\Mutasi;
use App\Models\User;

class InfakObserver
{
    /**
     * Handle the Infak "created" event.
     */
    public function created(Infak $infak): void
    {
        $dana_akhir = (float)$infak->nominal;

        if ($dana_akhir) {
            $infak_id = $infak->id;
            $cabang_id = $infak->cabang_id;
            $user_id = $infak->user_id ?? "";
            $umum = (float)config('bwi.persentase_saldo_umum')/100;
            $keamilan = (float)config('bwi.persentase_saldo_keamilan')/100;
            $csr = (float)config('bwi.persentase_saldo_csr')/100;
            $cadangan = (float)config('bwi.persentase_saldo_cadangan')/100;

            $dana_umum_akhir = $dana_akhir*$umum;
            $dana_keamilan_akhir = $dana_akhir*$keamilan;
            $dana_csr_akhir = $dana_akhir*$csr;
            $dana_cadangan_akhir = $dana_akhir*$cadangan;

            $user_infak = ($user_id) ? (User::find($user_id)->name)." " : ""; 
            
            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
            $last_mutasi_umum = (float)($last_mutasi->saldo_umum ?? 0);
            $last_mutasi_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
            $last_mutasi_csr = (float)($last_mutasi->saldo_csr ?? 0);
            $last_mutasi_cadangan = (float)($last_mutasi->saldo_cadangan ?? 0);

            $mutasi_2 = $last_mutasi_umum + $dana_umum_akhir;
            $mutasi_4 = $last_mutasi_keamilan + $dana_keamilan_akhir;
            $mutasi_6 = $last_mutasi_csr + $dana_csr_akhir;
            $mutasi_8 = $last_mutasi_cadangan + $dana_cadangan_akhir;

            // Create second entry
            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'kredit' => $dana_umum_akhir,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $last_mutasi_keamilan,
                'saldo_csr' => $last_mutasi_csr,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Penambahan Saldo Umum (".config('bwi.persentase_saldo_umum')."%) Infak dari ".$user_infak,
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'kredit' => $dana_keamilan_akhir,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $last_mutasi_csr,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Penambahan Saldo Keamilan (".config('bwi.persentase_saldo_keamilan')."%) Infak dari ".$user_infak,
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'kredit' => $dana_csr_akhir,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_6,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Penambahan Saldo CSR (".config('bwi.persentase_saldo_csr')."%) Infak dari ".$user_infak,
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'kredit' => $dana_csr_akhir,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_6,
                'saldo_cadangan' => $mutasi_8,
                'keterangan' => "Penambahan Saldo Cadangan (".config('bwi.persentase_saldo_cadangan')."%) Infak dari ".$user_infak,
            ]);
        }
    }

    /**
     * Handle the Infak "updated" event.
     */
    public function updated(Infak $infak): void
    {
        $dana_awal = (float)$infak->getOriginal('nominal');
        $dana_akhir = (float)$infak->nominal;

        if ($dana_akhir && ($dana_akhir != $dana_awal)) {
            $infak_id = $infak->id;
            $cabang_id = $infak->cabang_id;
            $user_id = $infak->user_id ?? "";
            $umum = (float)config('bwi.persentase_saldo_umum')/100;
            $keamilan = (float)config('bwi.persentase_saldo_keamilan')/100;
            $csr = (float)config('bwi.persentase_saldo_csr')/100;
            $cadangan = (float)config('bwi.persentase_saldo_cadangan')/100;

            $dana_umum = $dana_awal*$umum;
            $dana_keamilan = $dana_awal*$keamilan;
            $dana_csr = $dana_awal*$csr;
            $dana_cadangan = $dana_awal*$cadangan;

            $dana_umum_akhir = $dana_akhir*$umum;
            $dana_keamilan_akhir = $dana_akhir*$keamilan;
            $dana_csr_akhir = $dana_akhir*$csr;
            $dana_cadangan_akhir = $dana_akhir*$cadangan;

            $user_infak = ($user_id) ? (User::find($user_id)->name)." " : ""; 
            
            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
            $last_mutasi_umum = (float)($last_mutasi->saldo_umum ?? 0);
            $last_mutasi_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
            $last_mutasi_csr = (float)($last_mutasi->saldo_csr ?? 0);
            $last_mutasi_cadangan = (float)($last_mutasi->saldo_cadangan ?? 0);

            $mutasi_1 = $last_mutasi_umum - $dana_umum;
            $mutasi_2 = $mutasi_1 + $dana_umum_akhir;
            $mutasi_3 = $last_mutasi_keamilan - $dana_keamilan;
            $mutasi_4 = $mutasi_3 + $dana_keamilan_akhir;
            $mutasi_5 = $last_mutasi_csr - $dana_csr;
            $mutasi_6 = $mutasi_5 + $dana_csr_akhir;
            $mutasi_7 = $last_mutasi_cadangan - $dana_cadangan;
            $mutasi_8 = $mutasi_7 + $dana_cadangan_akhir;

            // Create first entry
            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'debet' => $dana_umum,
                'saldo_umum' => $mutasi_1,
                'saldo_keamilan' => $last_mutasi_keamilan,
                'saldo_csr' => $last_mutasi_csr,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Perubahan Saldo Umum (".config('bwi.persentase_saldo_umum')."%) Infak dari ".$user_infak."(lama)",
            ]);

            // Create second entry
            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'kredit' => $dana_umum_akhir,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $last_mutasi_keamilan,
                'saldo_csr' => $last_mutasi_csr,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Perubahan Saldo Umum (".config('bwi.persentase_saldo_umum')."%) Infak dari ".$user_infak."(baru)",
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'debet' => $dana_keamilan,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_3,
                'saldo_csr' => $last_mutasi_csr,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Perubahan Saldo Keamilan (".config('bwi.persentase_saldo_keamilan')."%) Infak dari ".$user_infak."(lama)",
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'kredit' => $dana_keamilan_akhir,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $last_mutasi_csr,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Perubahan Saldo Keamilan (".config('bwi.persentase_saldo_keamilan')."%) Infak dari ".$user_infak."(baru)",
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'debet' => $dana_csr,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_5,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Perubahan Saldo CSR (".config('bwi.persentase_saldo_csr')."%) Infak dari ".$user_infak."(lama)",
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'kredit' => $dana_csr_akhir,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_6,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Perubahan Saldo CSR (".config('bwi.persentase_saldo_csr')."%) Infak dari ".$user_infak."(baru)",
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'debet' => $dana_csr,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_6,
                'saldo_cadangan' => $mutasi_7,
                'keterangan' => "Perubahan Saldo Cadangan (".config('bwi.persentase_saldo_csr')."%) Infak dari ".$user_infak."(lama)",
            ]);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'infak_id' => $infak_id,
                'kredit' => $dana_csr_akhir,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_6,
                'saldo_cadangan' => $mutasi_8,
                'keterangan' => "Perubahan Saldo Cadangan (".config('bwi.persentase_saldo_csr')."%) Infak dari ".$user_infak."(baru)",
            ]);
        }
    }

    /**
     * Handle the Infak "deleted" event.
     */
    public function deleted(Infak $infak): void
    {
        //
    }

    /**
     * Handle the Infak "restored" event.
     */
    public function restored(Infak $infak): void
    {
        //
    }

    /**
     * Handle the Infak "force deleted" event.
     */
    public function forceDeleted(Infak $infak): void
    {
        //
    }
}
