<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Mutasi;
use App\Models\Cicilan;
use App\Models\Pinjaman;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

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
     * Once disbursed, terms may not be changed without a formal reversal.
     */
    public function updating(Pinjaman $pinjaman): void
    {
        if (! Mutasi::where('pinjaman_id', $pinjaman->id)->exists()) {
            return;
        }

        foreach (['acc_pinjaman', 'cabang_id', 'nama_kelompok', 'jumlah_anggota', 'list_anggota', 'nominal_bmpa_max', 'nominal_pinjaman', 'total_pinjaman', 'lama_cicilan', 'cicilan_kelompok', 'tanggal_cicilan_pertama', 'nama_pengurus', 'dengan_pengurus'] as $field) {
            if ($pinjaman->isDirty($field)) {
                throw ValidationException::withMessages([
                    'acc_pinjaman' => 'Pinjaman telah dibukukan dan tidak boleh diubah tanpa proses koreksi keuangan.',
                ]);
            }
        }

        // Validate membership before saving approval, so a rejected approval does
        // not leave the loan approved without its ledger entry.
        if ($pinjaman->isDirty('acc_pinjaman') && (int) $pinjaman->acc_pinjaman === 1) {
            $memberIds = collect($pinjaman->list_anggota ?? [])->pluck('user_id')->filter();
            if ($pinjaman->nama_pengurus) {
                $memberIds->push($pinjaman->nama_pengurus);
            }

            if ($memberIds->isEmpty() || User::whereIn('id', $memberIds->unique()->all())->where('is_kelompok', true)->exists()) {
                throw ValidationException::withMessages([
                    'acc_pinjaman' => 'Anggota atau pengurus masih memiliki pinjaman aktif, atau data anggota belum lengkap.',
                ]);
            }
        }
    }

    public function deleting(Pinjaman $pinjaman): void
    {
        if (Mutasi::where('pinjaman_id', $pinjaman->id)->exists() || Cicilan::where('pinjaman_id', $pinjaman->id)->exists()) {
            throw ValidationException::withMessages([
                'acc_pinjaman' => 'Pinjaman yang sudah dicairkan atau memiliki tagihan tidak boleh dihapus.',
            ]);
        }
    }

    /**
     * Handle the Pinjaman "updated" event.
     */
    public function updated(Pinjaman $pinjaman): void
    {
        // Prevent repeat disbursements or duplicate installment schedules.
        if (! $pinjaman->wasChanged('acc_pinjaman') || (int) $pinjaman->acc_pinjaman !== 1
            || Mutasi::where('pinjaman_id', $pinjaman->id)->exists()) {
            return;
        }

        //
        $totalPinjaman = (float)$pinjaman->total_pinjaman;

        if ($totalPinjaman && $pinjaman->acc_pinjaman && $pinjaman->tanggal_cicilan_pertama) {


            $userIds = $pinjaman->list_anggota;

            $counterPinjaman = 0;
            if ($pinjaman->nama_pengurus) {
                $nama_pengurus = User::find($pinjaman->nama_pengurus);
                if ($nama_pengurus->is_kelompok) {
                    $counterPinjaman += 1;
                }
            }

            foreach ($userIds as $userIdData) {
                $userId = $userIdData['user_id'];
                $user = User::find($userId);

                if ($user->is_kelompok) {
                    $counterPinjaman += 1;
                }
            }


            if ($counterPinjaman > 0) {
                throw ValidationException::withMessages([
                    'acc_pinjaman' => 'Setiap anggota harus menyelesaikan kelompok pinjaman sebelumnya.',
                ]);
            }

            if ($counterPinjaman == 0) {
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
                    'keterangan' => "Pinjaman kelompok " . $pinjaman->nama_kelompok,
                ]);


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

                $tglCicilan = $pinjaman->tanggal_cicilan_pertama;
                $lamaCicilan = (int)$pinjaman->lama_cicilan;

                if ($lamaCicilan && $tglCicilan) {
                    $nominalCicilan = (float)$pinjaman->total_pinjaman / $lamaCicilan;
                    for ($i = 1; $i <= $lamaCicilan; $i++) {
                        Cicilan::create([
                            'cabang_id' => $cabang_id,
                            'pinjaman_id' => $pinjaman_id,
                            'nominal_cicilan' => $nominalCicilan,
                            'tanggal_cicilan' => $tglCicilan,
                            'tagihan_ke' => $i,
                            'is_final' => ($i === $lamaCicilan) ? true : false,
                        ]);
                        $tglCicilan = date('Y-m-d', strtotime($tglCicilan . ' +1 week'));
                    }
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
