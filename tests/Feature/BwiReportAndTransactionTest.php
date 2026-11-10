<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Cicilan;
use App\Models\Infak;
use App\Models\Laporan;
use App\Models\Mutasi;
use App\Models\Pengeluaran;
use App\Models\Pinjaman;
use App\Models\User;
use App\Services\BwiMonthlyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BwiReportAndTransactionTest extends TestCase
{
    use RefreshDatabase;

    private function branch(): Cabang
    {
        return Cabang::create([
            'nama_cabang' => 'Cabang Uji PDF',
            'saldo_awal' => '1000000',
        ]);
    }

    public function test_private_report_is_only_downloadable_by_an_authorized_branch_user(): void
    {
        Storage::fake('local');

        $branch = $this->branch();
        $path = "bwi/reports/cabang-{$branch->id}/2026-10.pdf";
        Storage::disk('local')->put($path, '%PDF-demo');

        $report = Laporan::create([
            'cabang_id' => $branch->id,
            'jenis' => 'Laporan Bulanan',
            'tanggal' => '2026-10-01',
            'berkas' => $path,
        ]);

        $this->get(route('bwi.laporan.download', ['laporan' => $report->id]))
            ->assertRedirect();

        $other = User::factory()->create();
        $other->forceFill(['cabang_id' => $branch->id + 1])->save();
        $this->actingAs($other)->get(route('bwi.laporan.download', ['laporan' => $report->id]))
            ->assertForbidden();

        Role::findOrCreate('super_admin', 'web');
        Permission::findOrCreate('view_any_laporan', 'web');
        $other->assignRole('super_admin');
        $other->givePermissionTo('view_any_laporan');

        $this->actingAs($other)->get(route('bwi.laporan.download', ['laporan' => $report->id]))
            ->assertOk();
    }

    public function test_monthly_report_generation_writes_to_private_storage_and_is_idempotent(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $branch = $this->branch();

        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('output')->twice()->andReturn('%PDF-private');
        Pdf::shouldReceive('loadView')->twice()->andReturn($pdf);

        $service = app(BwiMonthlyReport::class);
        $period = CarbonImmutable::parse('2026-10-01');

        $first = $service->generate($branch->id, $period);
        $second = $service->generate($branch->id, $period);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(
            "bwi/reports/cabang-{$branch->id}/2026-10.pdf",
            $second->berkas
        );
        Storage::disk('local')->assertExists($second->berkas);
        Storage::disk('public')->assertMissing($second->berkas);
        $this->assertSame(1, Laporan::where('cabang_id', $branch->id)->count());
    }

    public function test_receivable_is_historical_even_after_loan_has_been_paid_in_full(): void
    {
        $branch = $this->branch();
        $opening = Mutasi::where('cabang_id', $branch->id)->oldest('id')->firstOrFail();
        Mutasi::whereKey($opening->id)->update(['created_at' => '2025-12-31 10:00:00']);

        $loan = Pinjaman::create([
            'cabang_id' => $branch->id,
            'nama_kelompok' => 'Kelompok Lunas Saat Ini',
            'status' => 'Lunas',
            'acc_pinjaman' => 1,
            'list_anggota' => [],
            'total_pinjaman' => '1000',
            'lama_cicilan' => 2,
        ]);
        $disbursement = Mutasi::create([
            'cabang_id' => $branch->id, 'pinjaman_id' => $loan->id,
            'debet' => '1000', 'saldo_umum' => '999000',
            'saldo_keamilan' => 0, 'saldo_csr' => 0, 'saldo_cadangan' => 0,
        ]);
        Mutasi::whereKey($disbursement->id)->update(['created_at' => '2026-01-10 09:00:00']);

        $payments = [];
        foreach ([1, 2] as $number) {
            $payments[$number] = Cicilan::create([
                'cabang_id' => $branch->id, 'pinjaman_id' => $loan->id,
                'nominal_cicilan' => '500', 'tagihan_ke' => $number,
                'status_cicilan' => 1, 'is_final' => $number === 2,
                'tanggal_cicilan' => '2026-04-10',
            ]);
        }
        foreach ([1 => '2026-04-10 09:00:00', 2 => '2026-06-10 09:00:00'] as $number => $when) {
            $mutasi = Mutasi::create([
                'cabang_id' => $branch->id, 'cicilan_id' => $payments[$number]->id,
                'kredit' => '500', 'saldo_umum' => 999000 + $number * 500,
                'saldo_keamilan' => 0, 'saldo_csr' => 0, 'saldo_cadangan' => 0,
            ]);
            Mutasi::whereKey($mutasi->id)->update(['created_at' => $when]);
        }

        $report = app(BwiMonthlyReport::class);
        $this->assertEqualsWithDelta(1000, $report->calculate($branch, CarbonImmutable::parse('2026-03-01'))['piutang'], 0.01);
        $this->assertEqualsWithDelta(500, $report->calculate($branch, CarbonImmutable::parse('2026-05-01'))['piutang'], 0.01);
        $this->assertEqualsWithDelta(0, $report->calculate($branch, CarbonImmutable::parse('2026-07-01'))['piutang'], 0.01);
    }

    public function test_legacy_pdf_migration_is_dry_run_by_default(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $branch = $this->branch();
        $record = Laporan::create([
            'cabang_id' => $branch->id,
            'jenis' => 'Laporan Bulanan',
            'tanggal' => '2026-09-01',
            'berkas' => 'Laporan September.pdf',
        ]);
        Storage::disk('public')->put('Laporan September.pdf', '%PDF-legacy');

        $this->artisan('bwi:secure-reports')->assertExitCode(0);
        $this->assertSame('Laporan September.pdf', $record->fresh()->berkas);

        $this->artisan('bwi:secure-reports', ['--execute' => true])->assertExitCode(0);
        $this->assertSame("bwi/reports/legacy/{$record->id}.pdf", $record->fresh()->berkas);
        Storage::disk('local')->assertExists($record->fresh()->berkas);
        Storage::disk('public')->assertMissing('Laporan September.pdf');
    }

    public function test_failed_expense_correction_rolls_back_source_and_ledger(): void
    {
        $branch = $this->branch();
        Infak::create([
            'cabang_id' => $branch->id, 'nominal' => '1000',
            'tanggal' => '2026-11-01',
        ]);
        $expense = Pengeluaran::create([
            'cabang_id' => $branch->id, 'nominal' => '10',
            'jenis' => 'Keamilan', 'tanggal' => '2026-11-01',
            'keterangan' => 'Biaya uji',
        ]);
        $before = Mutasi::where('cabang_id', $branch->id)->count();

        try {
            $expense->update(['jenis' => 'Unknown']);
            $this->fail('An invalid financial correction should be rejected.');
        } catch (ValidationException $e) {
            $this->assertSame('Keamilan', $expense->fresh()->jenis);
        }

        $this->assertSame($before, Mutasi::where('cabang_id', $branch->id)->count());
    }

    public function test_stale_donation_edit_is_rejected_without_new_mutations(): void
    {
        $branch = $this->branch();
        $donation = Infak::create([
            'cabang_id' => $branch->id, 'nominal' => '1000',
            'tanggal' => '2026-11-01',
        ]);
        $stale = Infak::findOrFail($donation->id);
        $donation->update(['nominal' => '2000']);
        $before = Mutasi::where('cabang_id', $branch->id)->count();

        try {
            $stale->update(['nominal' => '1500']);
            $this->fail('A stale edit should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame($before, Mutasi::where('cabang_id', $branch->id)->count());
        }
    }

    public function test_expense_overdraw_is_rejected_even_without_ui_validation(): void
    {
        $branch = $this->branch();
        $before = Mutasi::where('cabang_id', $branch->id)->count();

        try {
            Pengeluaran::create([
                'cabang_id' => $branch->id,
                'jenis' => 'Keamilan',
                'nominal' => '9999',
                'tanggal' => '2026-11-01',
                'keterangan' => 'Percobaan saldo kurang',
            ]);
            $this->fail('Insufficient funds must be rejected in the backend.');
        } catch (ValidationException $exception) {
            $this->assertSame($before, Mutasi::where('cabang_id', $branch->id)->count());
            $this->assertSame(0, Pengeluaran::where('cabang_id', $branch->id)->count());
        }
    }

    public function test_loan_approval_with_no_members_is_rejected_before_posting(): void
    {
        $branch = $this->branch();
        $loan = Pinjaman::create([
            'cabang_id' => $branch->id,
            'nama_kelompok' => 'Kelompok Kosong',
            'status' => 'Menunggu Verifikasi',
            'acc_pinjaman' => 0,
            'list_anggota' => [],
            'total_pinjaman' => '1000',
            'lama_cicilan' => 5,
            'tanggal_cicilan_pertama' => '2026-11-18',
        ]);

        try {
            $loan->update(['acc_pinjaman' => 1]);
            $this->fail('Missing loan members should fail before disbursement.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, (int) $loan->fresh()->acc_pinjaman);
            $this->assertSame(0, Mutasi::where('pinjaman_id', $loan->id)->count());
        }
    }

}
