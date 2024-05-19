<?php

namespace App\Observers;

use App\Models\Cicilan;
use App\Models\User;
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
        
    }

    /**
     * Handle the Pinjaman "updated" event.
     */
    public function updated(Pinjaman $pinjaman): void
    {
        //
        $totalPinjaman = (float)$pinjaman->total_pinjaman;

        if ($totalPinjaman && $pinjaman->acc_pinjaman && $pinjaman->tanggal_cicilan_pertama) {
            $pinjaman_id = $pinjaman->id;
            $cabang_id = $pinjaman->cabang_id;

            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
            $last_mutasi_umum = (float)($last_mutasi->saldo_umum ?? 0);
            $last_mutasi_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
            $last_mutasi_csr = (float)($last_mutasi->saldo_csr ?? 0);
            $last_mutasi_cadangan = (float)($last_mutasi->saldo_cadangan ?? 0);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'pinjaman_id' => $pinjaman_id,
                'debet' => $totalPinjaman,
                'saldo_umum' => $last_mutasi_umum - $totalPinjaman,
                'saldo_keamilan' => $last_mutasi_keamilan,
                'saldo_csr' => $last_mutasi_csr,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Pinjaman kelompok ".$pinjaman->nama_kelompok,
            ]);

            $lamaCicilan = (int)$pinjaman->lama_cicilan;
            $userIds = $pinjaman->list_anggota;

            if ($pinjaman->nama_pengurus) {
                $nama_pengurus = User::find($pinjaman->nama_pengurus);
                if ($nama_pengurus) {
                    $nama_pengurus->pinjaman_id = $pinjaman_id;
                    $nama_pengurus->is_kelompok = true;
                    $nama_pengurus->save();
                }
            }
            
            foreach ($userIds as $userIdData) {
                $userId = $userIdData['user_id'];
                $user = User::find($userId);

                if ($user) {
                    $user->pinjaman_id = $pinjaman_id;
                    $user->is_kelompok = true;
                    $user->save();
                }
                
            }

            $nominalCicilan = (float)$pinjaman->total_pinjaman / $lamaCicilan;
            $tglCicilan = $pinjaman->tanggal_cicilan_pertama;

            if ($lamaCicilan) {
                for ($i=1; $i <= $lamaCicilan; $i++) { 
                    Cicilan::create([
                        'cabang_id' => $cabang_id,
                        'pinjaman_id' => $pinjaman_id,
                        'nominal_cicilan' => $nominalCicilan,	
                        'tanggal_cicilan' => $tglCicilan,	
                        'tagihan_ke' => $i,	
                        'is_final' => ($i === $lamaCicilan) ? true : false,	
                    ]);
                    $tglCicilan = date('Y-m-d', strtotime($tglCicilan.' +1 week'));
                }
            }
        }
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
