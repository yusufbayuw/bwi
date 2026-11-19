<?php

namespace Tests\Feature;

use App\Models\Cabang;
use App\Models\Mutasi;
use App\Models\User;
use App\Services\CabangUserWorkbookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class CabangUserWorkbookTest extends TestCase
{
    use RefreshDatabase;

    private function workbook(array $branches, array $users = [], array $offices = []): string
    {
        $book = new Spreadsheet();
        $book->getActiveSheet()->setTitle('Cabang');
        $book->getActiveSheet()->fromArray([
            ['ref_cabang', 'id_cabang', 'nama_cabang', 'lokasi', 'saldo_awal'],
            ...$branches,
        ]);

        $sheet = $book->createSheet();
        $sheet->setTitle('Pengguna');
        $sheet->fromArray([
            [
                'ref_cabang', 'id_user', 'nama', 'email', 'username', 'jenis_anggota',
                'no_hp', 'nomor_ktp', 'nomor_kk', 'alamat', 'pekerjaan',
                'penghasilan_bulanan', 'role_tambahan', 'password_awal', 'roles_saat_ini',
            ],
            ...$users,
        ]);

        $sheet = $book->createSheet();
        $sheet->setTitle('Pengurus');
        $sheet->fromArray([
            ['ref_cabang', 'jabatan', 'identitas_user'],
            ...$offices,
        ]);

        $path = tempnam(sys_get_temp_dir(), 'bwi-test-xlsx');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    public function test_template_and_export_create_valid_xlsx_without_gridlines(): void
    {
        $service = app(CabangUserWorkbookService::class);

        foreach (['downloadTemplate', 'downloadExport'] as $method) {
            $response = $service->{$method}();
            $path = $response->getFile()->getPathname();

            try {
                $book = IOFactory::load($path);
                $this->assertSame(
                    ['Cabang', 'Pengguna', 'Pengurus', 'Panduan'],
                    $book->getSheetNames()
                );

                // PhpSpreadsheet 1.29 can re-enable gridlines while loading
                // an XLSX with printOptions/gridLinesSet. Assert the XLSX XML
                // actually delivered to Excel, rather than reader state.
                $zip = new ZipArchive();
                $this->assertTrue($zip->open($path) === true);
                foreach (range(1, 4) as $sheetNumber) {
                    $xml = $zip->getFromName("xl/worksheets/sheet{$sheetNumber}.xml");
                    $this->assertIsString($xml);
                    $this->assertMatchesRegularExpression(
                        '/<sheetView\\b[^>]*\\bshowGridLines="false"/',
                        $xml
                    );
                }
                $zip->close();

                $book->disconnectWorksheets();
            } finally {
                @unlink($path);
            }
        }
    }

    public function test_single_upload_creates_branch_users_officers_and_opening_mutation_once(): void
    {
        Role::findOrCreate('ketua_pengurus_cabang', 'web');
        $path = $this->workbook(
            [['CB01', '', 'Bandung Utara', 'Bandung', '1500000']],
            [
                ['CB01', '', 'Ketua Satu', 'ketua@example.test', '', 'Anggota', '', '', '', '', '', '', '', '', ''],
                ['CB01', '', 'Anggota Satu', 'anggota@example.test', '', 'Anggota', '', '', '', '', '', '', '', '', ''],
            ],
            [['CB01', 'ketua_pengurus', 'ketua@example.test']],
        );

        try {
            $service = app(CabangUserWorkbookService::class);
            $first = $service->import($path);
            $this->assertSame(1, $first['cabang_baru']);
            $this->assertSame(2, $first['pengguna_baru']);
            $this->assertSame(1, $first['kepengurusan_diperbarui']);

            $branch = Cabang::query()->sole();
            $head = User::query()->where('email', 'ketua@example.test')->sole();
            $this->assertSame($branch->id, $head->cabang_id);
            $this->assertSame((string) $head->id, (string) $branch->fresh()->ketua_pengurus);
            $this->assertTrue($head->hasRole('ketua_pengurus_cabang'));
            $this->assertSame(1, Mutasi::query()->where('cabang_id', $branch->id)->count());

            $second = $service->import($path);
            $this->assertSame(0, $second['cabang_baru']);
            $this->assertSame(0, $second['pengguna_baru']);
            $this->assertSame(1, Cabang::count());
            $this->assertSame(2, User::count());
            $this->assertSame(1, Mutasi::count());
        } finally {
            @unlink($path);
        }
    }

    public function test_invalid_user_rolls_back_branch_and_opening_mutation(): void
    {
        $path = $this->workbook(
            [['CB01', '', 'Cabang Baru', 'Bandung', '100000']],
            [['CB01', '', 'Tidak Sah', 'invalid-email', '', 'Anggota', '', '', '', '', '', '', '', '', '']],
        );

        try {
            $this->expectException(ValidationException::class);
            app(CabangUserWorkbookService::class)->import($path);
        } finally {
            @unlink($path);
            $this->assertSame(0, Cabang::count());
            $this->assertSame(0, User::count());
            $this->assertSame(0, Mutasi::count());
        }
    }

    public function test_existing_opening_balance_cannot_be_changed_through_import(): void
    {
        $branch = Cabang::create([
            'nama_cabang' => 'Cabang Eksisting',
            'lokasi' => 'Bandung',
            'saldo_awal' => '500000',
        ]);
        $path = $this->workbook([
            ['CB01', $branch->id, 'Cabang Eksisting', 'Bandung', '750000'],
        ]);

        try {
            $this->expectException(ValidationException::class);
            app(CabangUserWorkbookService::class)->import($path);
        } finally {
            @unlink($path);
            $this->assertSame('500000', (string) $branch->fresh()->saldo_awal);
            $this->assertSame(1, Mutasi::count());
        }
    }

    public function test_update_existing_user_does_not_reset_password_or_unrelated_role(): void
    {
        $branch = Cabang::create([
            'nama_cabang' => 'Cabang Satu',
            'lokasi' => 'Bandung',
            'saldo_awal' => '0',
        ]);
        Role::findOrCreate('panel_user', 'web');
        $user = User::factory()->create([
            'email' => 'existing@example.test',
            'password' => 'old-secret',
        ]);
        $user->forceFill(['cabang_id' => $branch->id])->save();
        $user->assignRole('panel_user');
        $oldPassword = $user->password;

        $path = $this->workbook(
            [['CB01', $branch->id, 'Cabang Satu', 'Bandung', '0']],
            [['CB01', $user->id, 'Nama Diperbarui', 'existing@example.test', '', '', '', '', '', '', '', '', '', '', '']],
        );

        try {
            app(CabangUserWorkbookService::class)->import($path);
            $user->refresh();
            $this->assertSame('Nama Diperbarui', $user->name);
            $this->assertSame($oldPassword, $user->password);
            $this->assertTrue($user->hasRole('panel_user'));
        } finally {
            @unlink($path);
        }
    }
}
