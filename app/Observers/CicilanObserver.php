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
            $last_mutasi_cadangan = (float)($last_mutasi->saldo_cadangan ?? 0);

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'cicilan_id' => $cicilan_id,
                'kredit' => $nominal,
                'saldo_umum' => $last_mutasi_umum + $nominal,
                'saldo_keamilan' => $last_mutasi_keamilan,
                'saldo_csr' => $last_mutasi_csr,
                'saldo_cadangan' => $last_mutasi_cadangan,
                'keterangan' => "Cicilan ke-" . $cicilan->tagihan_ke . " kelompok " . Pinjaman::find($cicilan->pinjaman_id)->nama_kelompok,
            ]);

            if ($cicilan->is_final) {
                $lunas = Pinjaman::find($cicilan->pinjaman_id);
                $lunas->status = "Lunas";
                //is_organ adalah sudah lunas/belum
                $lunas->is_organ = true;
                $lunas->saveQuietly();

                $userIds = Pinjaman::find($cicilan->pinjaman_id)->list_anggota;
                $id_pengurus = Pinjaman::find($cicilan->pinjaman_id)->nama_pengurus;

                if ($id_pengurus) {
                    $nama_pengurus = User::find($id_pengurus);
                    if ($nama_pengurus) {
                        $nama_pengurus->pinjaman_id = null;
                        $nama_pengurus->is_kelompok = false;
                        $nama_pengurus->bmpa_gain_counter = $nama_pengurus->bmpa_gain_counter + 1;
                        $nama_pengurus->save();
                    }
                }

                foreach ($userIds as $userIdData) {
                    $userId = $userIdData['user_id'];
                    $user = User::find($userId);

                    if ($user) {
                        $user->pinjaman_id = null;
                        $user->is_kelompok = false;
                        //$user->bmpa = (float)$user->bmpa + 500000; //automatic bmpa
                        $user->bmpa_gain_counter = $user->bmpa_gain_counter + 1;
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
