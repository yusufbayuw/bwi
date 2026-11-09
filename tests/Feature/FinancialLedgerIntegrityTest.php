<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Cicilan;
use App\Models\Infak;
use App\Models\Mutasi;
use App\Models\Pengeluaran;
use App\Models\Pinjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialLedgerIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function branch(): Cabang
    {
        return Cabang::create([
            'nama_cabang' => 'Cabang Uji',
            'saldo_awal' => '1000000',
        ]);
    }

    public function test_donation_corrections_keep_reserve_balance_and_cannot_be_deleted(): void
    {
        $branch = $this->branch();

        $infak = Infak::create([
            'cabang_id' => $branch->id,
            'nominal' => '1000',
            'tanggal' => '2026-11-01',
        ]);

        $this->assertSame(4, Mutasi::where('infak_id', $infak->id)->count());

        $infak->update(['nominal' => '2000']);

        $this->assertSame(12, Mutasi::where('infak_id', $infak->id)->count());
        $balance = Mutasi::where('cabang_id', $branch->id)->latest('id')->first();
        $this->assertEqualsWithDelta(100, (float) $balance->saldo_cadangan, 0.01);
        $this->assertEqualsWithDelta(100, (float) $balance->kredit, 0.01);

        $this->expectException(ValidationException::class);
        $infak->delete();
    }

    public function test_expense_reclassification_restores_old_fund_without_losing_reserve(): void
    {
        $branch = $this->branch();
        Infak::create([
            'cabang_id' => $branch->id,
            'nominal' => '1000',
            'tanggal' => '2026-11-01',
        ]);

        $expense = Pengeluaran::create([
            'cabang_id' => $branch->id,
            'nominal' => '10',
            'jenis' => 'Keamilan',
            'tanggal' => '2026-11-01',
            'keterangan' => 'Biaya uji',
        ]);

        $expense->update(['jenis' => 'CSR', 'nominal' => '15']);
        $balance = Mutasi::where('cabang_id', $branch->id)->latest('id')->first();

        $this->assertEqualsWithDelta(125, (float) $balance->saldo_keamilan, 0.01);
        $this->assertEqualsWithDelta(10, (float) $balance->saldo_csr, 0.01);
        $this->assertEqualsWithDelta(50, (float) $balance->saldo_cadangan, 0.01);
    }

    public function test_paid_installment_does_not_post_twice_when_proof_is_updated(): void
    {
        $branch = $this->branch();
        $loan = Pinjaman::create([
            'cabang_id' => $branch->id,
            'nama_kelompok' => 'Kelompok Uji',
            'status' => 'Cicilan Berjalan',
            'acc_pinjaman' => 1,
            'list_anggota' => [],
            'total_pinjaman' => '1000',
            'lama_cicilan' => 1,
        ]);
        $installment = Cicilan::create([
            'cabang_id' => $branch->id,
            'pinjaman_id' => $loan->id,
            'nominal_cicilan' => '1000',
            'tagihan_ke' => 1,
            'status_cicilan' => 0,
            'tanggal_cicilan' => '2026-11-01',
            'is_final' => 0,
        ]);

        $installment->update([
            'status_cicilan' => 1,
            'tanggal_bayar' => '2026-11-01',
        ]);
        $this->assertSame(1, Mutasi::where('cicilan_id', $installment->id)->count());

        $installment->update(['berkas' => 'bukti.png']);
        $this->assertSame(1, Mutasi::where('cicilan_id', $installment->id)->count());

        $this->expectException(ValidationException::class);
        $installment->update(['status_cicilan' => 0]);
    }

    public function test_branch_with_financial_history_cannot_be_deleted(): void
    {
        $branch = $this->branch();
        Infak::create([
            'cabang_id' => $branch->id,
            'nominal' => '1000',
            'tanggal' => '2026-11-01',
        ]);

        $this->expectException(ValidationException::class);
        $branch->delete();
    }
}
