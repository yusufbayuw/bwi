<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Cicilan;
use App\Models\Laporan;
use App\Models\Mutasi;
use App\Models\Pinjaman;
use App\Models\User;
use App\Policies\LaporanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BwiWorkflowProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeBranch(): Cabang
    {
        return Cabang::create([
            'nama_cabang' => 'Cabang Pengujian',
            'saldo_awal' => '1000000',
        ]);
    }

    private function members(Cabang $branch, int $count = 5): array
    {
        $members = [];
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();
            $user->forceFill([
                'cabang_id' => $branch->id,
                'is_kelompok' => false,
                'bmpa_gain_counter' => 0,
                'bmpa' => 500000,
            ])->save();
            $members[] = $user;
        }

        return $members;
    }

    private function loan(Cabang $branch, array $members, int $amount = 1000): Pinjaman
    {
        return Pinjaman::create([
            'cabang_id' => $branch->id,
            'nama_kelompok' => 'Kelompok '.uniqid(),
            'status' => 'Menunggu Verifikasi',
            'acc_pinjaman' => false,
            'jumlah_anggota' => count($members),
            'dengan_pengurus' => false,
            'list_anggota' => array_map(fn (User $user) => ['user_id' => $user->id], $members),
            'total_pinjaman' => (string) $amount,
            'lama_cicilan' => 5,
            'tanggal_cicilan_pertama' => '2026-12-01',
        ]);
    }

    public function test_generated_monthly_report_cannot_be_modified_or_deleted_manually(): void
    {
        $branch = $this->makeBranch();

        $report = Laporan::withoutEvents(fn () => Laporan::create([
            'cabang_id' => $branch->id,
            'tanggal' => '2026-10-01',
            'jenis' => 'Laporan Bulanan',
            'berkas' => "bwi/reports/cabang-{$branch->id}/2026-10.pdf",
        ]));

        $user = User::factory()->create();
        Permission::findOrCreate('update_laporan', 'web');
        Permission::findOrCreate('delete_laporan', 'web');
        Permission::findOrCreate('delete_any_laporan', 'web');
        $user->givePermissionTo(['update_laporan', 'delete_laporan', 'delete_any_laporan']);

        $policy = new LaporanPolicy();
        $this->assertFalse($policy->update($user, $report));
        $this->assertFalse($policy->delete($user, $report));
        $this->assertFalse($policy->deleteAny($user));

        try {
            $report->update(['tanggal' => '2026-09-01']);
            $this->fail('Report archive was modified.');
        } catch (ValidationException $exception) {
            $this->assertSame('2026-10-01', $report->fresh()->tanggal);
        }

        try {
            $report->delete();
            $this->fail('Report archive was deleted.');
        } catch (ValidationException $exception) {
            $this->assertDatabaseHas('laporans', ['id' => $report->id]);
        }

        try {
            Laporan::create([
                'cabang_id' => $branch->id,
                'tanggal' => '2026-08-01',
                'jenis' => 'Laporan Bulanan',
                'berkas' => 'fake.pdf',
            ]);
            $this->fail('User-created monthly reports should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertSame(1, Laporan::count());
        }
    }

    public function test_branch_staff_can_download_own_report_but_not_another_branch_report(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $own = $this->makeBranch();
        $other = Cabang::create(['nama_cabang' => 'Cabang Tetangga', 'saldo_awal' => 0]);

        $report = Laporan::withoutEvents(fn () => Laporan::create([
            'cabang_id' => $own->id,
            'tanggal' => '2026-10-01',
            'jenis' => 'Laporan Bulanan',
            'berkas' => "bwi/reports/cabang-{$own->id}/2026-10.pdf",
        ]));
        \Illuminate\Support\Facades\Storage::disk('local')->put($report->berkas, '%PDF-archive');

        \Spatie\Permission\Models\Role::findOrCreate('bendahara_cabang', 'web');
        Permission::findOrCreate('view_laporan', 'web');
        $staff = User::factory()->create();
        $staff->forceFill(['cabang_id' => $own->id])->save();
        $staff->assignRole('bendahara_cabang');
        $staff->givePermissionTo('view_laporan');

        $url = route('bwi.laporan.download', ['laporan' => $report->id]);
        $this->actingAs($staff)->get($url)->assertOk();

        $staff->forceFill(['cabang_id' => $other->id])->save();
        $this->actingAs($staff)->get($url)->assertForbidden();
    }

    public function test_existing_nonautomatic_report_remains_editable(): void
    {
        $branch = $this->makeBranch();
        $report = Laporan::create([
            'cabang_id' => $branch->id,
            'tanggal' => '2026-10-01',
            'jenis' => 'Dokumen Lain',
            'berkas' => 'lampiran.pdf',
        ]);

        $report->update(['berkas' => 'lampiran-baru.pdf']);
        $this->assertSame('lampiran-baru.pdf', $report->fresh()->berkas);
    }

    public function test_approval_posts_once_and_last_installment_releases_group_once(): void
    {
        $branch = $this->makeBranch();
        $members = $this->members($branch);
        $loan = $this->loan($branch, $members);

        $loan->update(['acc_pinjaman' => true, 'status' => 'Cicilan Berjalan']);

        $this->assertSame(1, Mutasi::where('pinjaman_id', $loan->id)->count());
        $instalments = Cicilan::where('pinjaman_id', $loan->id)
            ->orderBy('tagihan_ke')->get();
        $this->assertCount(5, $instalments);
        $this->assertSame(1, $instalments->where('is_final', true)->count());

        $loan->update(['status' => 'Cicilan Berjalan']);
        $this->assertSame(1, Mutasi::where('pinjaman_id', $loan->id)->count());
        $this->assertSame(5, Cicilan::where('pinjaman_id', $loan->id)->count());

        foreach ($instalments as $index => $instalment) {
            $instalment->update([
                'status_cicilan' => true,
                'tanggal_bayar' => '2027-01-'.sprintf('%02d', $index + 1),
            ]);
        }

        $this->assertSame('Lunas', $loan->fresh()->status);
        $this->assertSame(5, Mutasi::whereNotNull('cicilan_id')
            ->where('cabang_id', $branch->id)->count());

        foreach ($members as $member) {
            $fresh = $member->fresh();
            $this->assertFalse((bool) $fresh->is_kelompok);
            $this->assertNull($fresh->pinjaman_id);
            $this->assertSame(1, (int) $fresh->bmpa_gain_counter);
        }

        // A later proof update may not create money or BMPA entitlement twice.
        $instalments->last()->update(['berkas' => 'bukti-pelunasan.pdf']);
        $this->assertSame(5, Mutasi::whereNotNull('cicilan_id')
            ->where('cabang_id', $branch->id)->count());
        foreach ($members as $member) {
            $this->assertSame(1, (int) $member->fresh()->bmpa_gain_counter);
        }
    }

    public function test_second_loan_is_rejected_if_first_approval_consumed_available_cash(): void
    {
        $branch = $this->makeBranch();
        $first = $this->loan($branch, $this->members($branch), 600000);
        $second = $this->loan($branch, $this->members($branch), 600000);

        $first->update(['acc_pinjaman' => true]);
        $this->assertSame(1, Mutasi::where('pinjaman_id', $first->id)->count());

        try {
            $second->update(['acc_pinjaman' => true]);
            $this->fail('Approval must not overspend available funds.');
        } catch (ValidationException $exception) {
            $this->assertSame(0, (int) $second->fresh()->acc_pinjaman);
            $this->assertSame(0, Mutasi::where('pinjaman_id', $second->id)->count());
        }

        $this->assertEqualsWithDelta(
            400000,
            (float) Mutasi::where('cabang_id', $branch->id)
                ->orderByDesc('id')->value('saldo_umum'),
            0.01
        );
    }
}
