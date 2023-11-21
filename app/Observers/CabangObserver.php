<?php

namespace App\Observers;

use App\Models\Cabang;
use App\Models\Mutasi;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CabangObserver //implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Cabang "created" event.
     */
    public function created(Cabang $cabang): void
    {
        if ($cabang->saldo_awal) {
            $mutasi = Mutasi::create([
                'cabang_id' => $cabang->id,
                'debet' => $cabang->saldo_awal,
                'saldo_umum' => $cabang->saldo_awal,
                'keterangan' => "Dana awal Cabang"
            ]);
        }
    }

    /**
     * Handle the Cabang "updated" event.
     */
    public function updated(Cabang $cabang): void
    {
        if ($cabang->saldo_awal) {
            $cabang_id = $cabang->id;
            $dana_awal = $cabang->getOriginal('saldo_awal');
            $mutasi_old = (int)(Mutasi::where('cabang_id', $cabang_id)->latest()->first()->saldo_umum ?? 0) - (int)($dana_awal);

            // Create first entry
            $mutasi1 = Mutasi::create([
                'cabang_id' => $cabang_id,
                'kredit' => $dana_awal,
                'saldo_umum' => $mutasi_old,
                'keterangan' => "Perubahan dana awal lama Cabang",
            ]);

            // Create second entry
            $mutasi2 = Mutasi::create([
                'cabang_id' => $cabang_id,
                'debet' => $cabang->saldo_awal,
                'saldo_umum' => $mutasi_old + (int)($cabang->saldo_awal),
                'keterangan' => "Perubahan dana awal baru Cabang",
            ]);
        }
    }

    /**
     * Handle the Cabang "deleted" event.
     */
    public function deleted(Cabang $cabang): void
    {
        //
    }

    /**
     * Handle the Cabang "restored" event.
     */
    public function restored(Cabang $cabang): void
    {
        //
    }

    /**
     * Handle the Cabang "force deleted" event.
     */
    public function forceDeleted(Cabang $cabang): void
    {
        //
    }
}
