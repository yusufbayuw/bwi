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
                'keterangan' => "Dana awal cabang"
            ]);
        }
    }

    /**
     * Handle the Cabang "updated" event.
     */
    public function updated(Cabang $cabang): void
    {
        if ($cabang->saldo_awal && ($cabang->saldo_awal != $cabang->getOriginal('saldo_awal'))) {
            $cabang_id = $cabang->getOriginal('id');
            $dana_awal = $cabang->getOriginal('saldo_awal');
            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
        
            if ($last_mutasi) {
                $mutasi_old = (float)($last_mutasi->saldo_umum ?? 0) - (float)($dana_awal);
                $mutasi_old_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
                $mutasi_old_csr = (float)($last_mutasi->saldo_csr ?? 0);
        
                // Create first entry
                $mutasi1 = Mutasi::create([
                    'cabang_id' => $cabang_id,
                    'debet' => $dana_awal,
                    'saldo_umum' => $mutasi_old,
                    'saldo_keamilan' => $mutasi_old_keamilan,
                    'saldo_csr' => $mutasi_old_csr,
                    'keterangan' => "Perubahan dana awal cabang (lama)",
                ]);
        
                // Create second entry
                $mutasi2 = Mutasi::create([
                    'cabang_id' => $cabang_id,
                    'kredit' => $cabang->saldo_awal,
                    'saldo_umum' => $mutasi_old + (float)($cabang->saldo_awal),
                    'saldo_keamilan' => $mutasi_old_keamilan,
                    'saldo_csr' => $mutasi_old_csr,
                    'keterangan' => "Perubahan dana awal cabang (baru)",
                ]);
        
                // Additional logic can be added here based on your requirements
            } else {
                // Handle the case when $last_mutasi is null
                // You may want to log an error or handle this situation appropriately
            }
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
