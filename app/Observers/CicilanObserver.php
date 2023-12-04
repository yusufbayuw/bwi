<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Mutasi;
use App\Models\Cicilan;
use App\Models\Pinjaman;

class CicilanObserver
{
    /**
     * Handle the Cicilan "created" event.
     */
    public function created(Cicilan $cicilan): void
    {
        //
    }

    /**
     * Handle the Cicilan "updated" event.
     */
    public function updated(Cicilan $cicilan): void
    {
        $status = $cicilan->status_cicilan;

        if ($status) {
            $cicilan_id = $cicilan->id;
            $cabang_id = $cicilan->cabang_id;
            $nominal = (float)$cicilan->nominal_cicilan;

            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
            $last_mutasi_umum = (float)($last_mutasi->saldo_umum ?? 0);
            $last_mutasi_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
            $last_mutasi_csr = (float)($last_mutasi->saldo_csr ?? 0);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'cicilan_id' => $cicilan_id,
                'kredit' => $nominal,
                'saldo_umum' => $last_mutasi_umum + $nominal,
                'saldo_keamilan' => $last_mutasi_keamilan,
                'saldo_csr' => $last_mutasi_csr,
                'keterangan' => "Cicilan ke-".$cicilan->tagihan_ke." kelompok ".Pinjaman::find($cicilan->pinjaman_id)->nama_kelompok,
            ]);

            if ($cicilan->is_final) {
            $userIds = Pinjaman::find($cicilan->pinjaman_id)->list_anggota;
            
            foreach ($userIds as $userIdData) {
                $userId = $userIdData['user_id'];
                $user = User::find($userId);

                if ($user) {
                    $user->pinjaman_id = null;
                    $user->is_kelompok = false;
                    $user->save();
                }
                
            }
            }
        }
    }

    /**
     * Handle the Cicilan "deleted" event.
     */
    public function deleted(Cicilan $cicilan): void
    {
        //
    }

    /**
     * Handle the Cicilan "restored" event.
     */
    public function restored(Cicilan $cicilan): void
    {
        //
    }

    /**
     * Handle the Cicilan "force deleted" event.
     */
    public function forceDeleted(Cicilan $cicilan): void
    {
        //
    }
}
