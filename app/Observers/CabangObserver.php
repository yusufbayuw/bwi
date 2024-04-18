<?php

namespace App\Observers;

use App\Models\Cabang;
use App\Models\Mutasi;
use App\Models\User;
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
                $mutasi_old_cadangan = (float)($last_mutasi->saldo_cadangan ?? 0);

                // Create first entry
                $mutasi1 = Mutasi::create([
                    'cabang_id' => $cabang_id,
                    'debet' => $dana_awal,
                    'saldo_umum' => $mutasi_old,
                    'saldo_keamilan' => $mutasi_old_keamilan,
                    'saldo_csr' => $mutasi_old_csr,
                    'saldo_cadangan' => $mutasi_old_cadangan,
                    'keterangan' => "Perubahan dana awal cabang (lama)",
                ]);

                // Create second entry
                $mutasi2 = Mutasi::create([
                    'cabang_id' => $cabang_id,
                    'kredit' => $cabang->saldo_awal,
                    'saldo_umum' => $mutasi_old + (float)($cabang->saldo_awal),
                    'saldo_keamilan' => $mutasi_old_keamilan,
                    'saldo_csr' => $mutasi_old_csr,
                    'saldo_cadangan' => $mutasi_old_cadangan,
                    'keterangan' => "Perubahan dana awal cabang (baru)",
                ]);

                // Additional logic can be added here based on your requirements
            } else {
                // Handle the case when $last_mutasi is null
                // You may want to log an error or handle this situation appropriately
            }
        }

        if ($cabang->ketua_pembina != $cabang->getOriginal('ketua_pembina')) {
            if ($cabang->ketua_pembina) {
                $ketua_pembina_baru = User::find($cabang->ketua_pembina);
                $ketua_pembina_baru->assignRole(config('bwi.ketua_pembina'));
                $ketua_pembina_baru->is_organ = true;
                $ketua_pembina_baru->save();
            }
            if ($cabang->getOriginal('ketua_pembina')) {
                $ketua_pembina_lama = User::find($cabang->getOriginal('ketua_pembina'));
                $ketua_pembina_lama->roles()->detach();
                $ketua_pembina_lama->is_organ = false;
                $ketua_pembina_lama->save();
            }
        }

        if ($cabang->ketua_pengawas != $cabang->getOriginal('ketua_pengawas')) {
            if ($cabang->ketua_pengawas) {
                $ketua_pengawas_baru = User::find($cabang->ketua_pengawas);
                $ketua_pengawas_baru->assignRole(config('bwi.ketua_pengawas'));
                $ketua_pengawas_baru->is_organ = true;
                $ketua_pengawas_baru->save();
            }
            if ($cabang->getOriginal('ketua_pengawas')) {
                $ketua_pengawas_lama = User::find($cabang->getOriginal('ketua_pengawas'));
                $ketua_pengawas_lama->roles()->detach();
                $ketua_pengawas_lama->is_organ = false;
                $ketua_pengawas_lama->save();
            }
        }

        if ($cabang->ketua_pengurus != $cabang->getOriginal('ketua_pengurus')) {
            if ($cabang->ketua_pengurus) {
                $ketua_pengurus_baru = User::find($cabang->ketua_pengurus);
                $ketua_pengurus_baru->assignRole(config('bwi.ketua_pengurus'));
                $ketua_pengurus_baru->is_organ = true;
                $ketua_pengurus_baru->save();
            }
            if ($cabang->getOriginal('ketua_pengurus')) {
                $ketua_pengurus_lama = User::find($cabang->getOriginal('ketua_pengurus'));
                $ketua_pengurus_lama->roles()->detach();
                $ketua_pengurus_lama->is_organ = false;
                $ketua_pengurus_lama->save();
            }
        }

        if ($cabang->anggota_pembina != $cabang->getOriginal('anggota_pembina')) {
            if ($cabang->getOriginal('anggota_pembina')) {
                foreach ($cabang->getOriginal('anggota_pembina') as $key => $value) {
                    if ($value["nama"]) {
                        $anggota = User::find($value["nama"]);
                        $anggota->roles()->detach();
                        $anggota->is_organ = false;
                        $anggota->save();
                    }
                }
            }
            if ($cabang->anggota_pembina) {
                foreach ($cabang->anggota_pembina as $key => $value) {
                    if ($value["nama"]) {
                        $anggota = User::find($value["nama"]);
                        $anggota->assignRole(config('bwi.anggota_pembina'));
                        $anggota->is_organ = true;
                        $anggota->save();
                    }
                }
            }
        }

        if ($cabang->anggota_pengawas != $cabang->getOriginal('anggota_pengawas')) {
            if ($cabang->getOriginal('anggota_pengawas')) {
                foreach ($cabang->getOriginal('anggota_pengawas') as $key => $value) {
                    if ($value["nama"]) {
                        $anggota = User::find($value["nama"]);
                        $anggota->roles()->detach();
                        $anggota->is_organ = false;
                        $anggota->save();
                    }
                }
            }
            if ($cabang->anggota_pengawas) {
                foreach ($cabang->anggota_pengawas as $key => $value) {
                    if ($value["nama"]) {
                        $anggota = User::find($value["nama"]);
                        $anggota->assignRole(config('bwi.anggota_pengawas'));
                        $anggota->is_organ = true;
                        $anggota->save();
                    }
                }
            }
        }

        if ($cabang->sekretaris != $cabang->getOriginal('sekretaris')) {

            if ($cabang->getOriginal('sekretaris')) {
                foreach ($cabang->getOriginal('sekretaris') as $key => $value) {
                    if ($value["nama"]) {
                        $anggota = User::find($value["nama"]);
                        $anggota->roles()->detach();
                        $anggota->is_organ = false;
                        $anggota->save();
                    }
                }
            }
            if ($cabang->sekretaris) {
                foreach ($cabang->sekretaris as $key => $value) {
                    if ($value["nama"]) {
                        $anggota = User::find($value["nama"]);
                        $anggota->assignRole(config('bwi.sekretaris'));
                        $anggota->is_organ = true;
                        $anggota->save();
                    }
                }
            }
        }

        if ($cabang->bendahara != $cabang->getOriginal('bendahara')) {
            if ($cabang->getOriginal('bendahara')) {
                foreach ($cabang->getOriginal('bendahara') as $key => $value) {
                    if ($value["nama"]) {
                        $anggota = User::find($value["nama"]);
                        $anggota->roles()->detach();
                        $anggota->is_organ = false;
                        $anggota->save();
                    }
                }
            }
            if ($cabang->bendahara) {
                foreach ($cabang->bendahara as $key => $value) {
                    if ($value["nama"]) {
                        $anggota = User::find($value["nama"]);
                        $anggota->assignRole(config('bwi.bendahara'));
                        $anggota->is_organ = true;
                        $anggota->save();
                    }
                }
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
