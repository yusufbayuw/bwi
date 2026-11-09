<?php

namespace App\Observers;

use App\Models\Mutasi;
use App\Models\Pengeluaran;
use Illuminate\Validation\ValidationException;

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
            $last_mutasi_cadangan = (float)($last_mutasi->saldo_cadangan ?? 0);

            $mutasi_2 = ($jenis === "Umum") ? $last_mutasi_umum - $pengeluaran_nominal : $last_mutasi_umum;
            $mutasi_4 = ($jenis === "Keamilan") ? $last_mutasi_keamilan - $pengeluaran_nominal : $last_mutasi_keamilan;
            $mutasi_6 = ($jenis === "CSR") ? $last_mutasi_csr - $pengeluaran_nominal : $last_mutasi_csr;
            $mutasi_8 = ($jenis === "Cadangan") ? $last_mutasi_cadangan - $pengeluaran_nominal : $last_mutasi_cadangan;

            Mutasi::create([
                'cabang_id' => $cabang_id,
                'pengeluaran_id' => $pengeluaran_id,
                'debet' => $pengeluaran_nominal,
                'saldo_umum' => $mutasi_2,
                'saldo_keamilan' => $mutasi_4,
                'saldo_csr' => $mutasi_6,
                'saldo_cadangan' => $mutasi_8,
                'keterangan' => "Pengeluaran ".$jenis.": ".$pengeluaran->keterangan,
            ]);
        }
    }

    public function updating(Pengeluaran $pengeluaran): void
    {
        if ($pengeluaran->isDirty('cabang_id') && Mutasi::where('pengeluaran_id', $pengeluaran->id)->exists()) {
            throw ValidationException::withMessages(['cabang_id' => 'Cabang transaksi yang sudah dibukukan tidak boleh diganti.']);
        }
    }

    public function deleting(Pengeluaran $pengeluaran): void
    {
        if (Mutasi::where('pengeluaran_id', $pengeluaran->id)->exists()) {
            throw ValidationException::withMessages(['nominal' => 'Pengeluaran yang sudah dibukukan tidak boleh dihapus tanpa koreksi keuangan.']);
        }
    }

    /**
     * Handle the Pengeluaran "updated" event.
     */
    public function updated(Pengeluaran $pengeluaran): void
    {
        $pengeluaran_nominal = (float)$pengeluaran->nominal;
        $pengeluaran_nominal_lama = (float)$pengeluaran->getOriginal('nominal');

        if ($pengeluaran_nominal != $pengeluaran_nominal_lama || $pengeluaran->wasChanged('jenis')) {
            $pengeluaran_id = $pengeluaran->getOriginal('id');
            $cabang_id = $pengeluaran->cabang_id;
            $jenis = $pengeluaran->jenis;

            $last_mutasi = Mutasi::where('cabang_id', $cabang_id)->orderBy('id', 'DESC')->first();
            $last_mutasi_umum = (float)($last_mutasi->saldo_umum ?? 0);
            $last_mutasi_keamilan = (float)($last_mutasi->saldo_keamilan ?? 0);
            $last_mutasi_csr = (float)($last_mutasi->saldo_csr ?? 0);

            $jenis_lama = $pengeluaran->getOriginal('jenis');
            $balances = [
                'Umum' => $last_mutasi_umum,
                'Keamilan' => $last_mutasi_keamilan,
                'CSR' => $last_mutasi_csr,
                'Cadangan' => (float) ($last_mutasi->saldo_cadangan ?? 0),
            ];
            if (! array_key_exists($jenis_lama, $balances) || ! array_key_exists($jenis, $balances)) {
                throw ValidationException::withMessages(['jenis' => 'Jenis pengeluaran tidak dikenal.']);
            }

            $balances[$jenis_lama] += $pengeluaran_nominal_lama;
            $restored = $balances;
            $balances[$jenis] -= $pengeluaran_nominal;

            $snapshot = fn (array $values) => [
                'saldo_umum' => $values['Umum'],
                'saldo_keamilan' => $values['Keamilan'],
                'saldo_csr' => $values['CSR'],
                'saldo_cadangan' => $values['Cadangan'],
            ];

            Mutasi::create(array_merge([
                'cabang_id' => $cabang_id,
                'pengeluaran_id' => $pengeluaran_id,
                'kredit' => $pengeluaran_nominal_lama,
                'keterangan' => "Perubahan Pengeluaran (lama) ".$jenis_lama.": ".$pengeluaran->keterangan,
            ], $snapshot($restored)));

            Mutasi::create(array_merge([
                'cabang_id' => $cabang_id,
                'pengeluaran_id' => $pengeluaran_id,
                'debet' => $pengeluaran_nominal,
                'keterangan' => "Perubahan Pengeluaran (baru) ".$jenis.": ".$pengeluaran->keterangan,
            ], $snapshot($balances)));
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
