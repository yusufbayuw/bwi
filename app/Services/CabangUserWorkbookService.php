<?php

namespace App\Services;

use App\Models\Cabang;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * One XLSX file for branch initialization and repeatable user / organization updates.
 *
 * Import order: Cabang -> Pengguna -> Pengurus. No finance records are imported;
 * the existing CabangObserver creates the opening ledger entry only on creation.
 */
class CabangUserWorkbookService
{
    private const HEADERS = [
        'Cabang' => ['ref_cabang', 'id_cabang', 'nama_cabang', 'lokasi', 'saldo_awal'],
        'Pengguna' => [
            'ref_cabang', 'id_user', 'nama', 'email', 'username', 'jenis_anggota',
            'no_hp', 'nomor_ktp', 'nomor_kk', 'alamat', 'pekerjaan',
            'penghasilan_bulanan', 'role_tambahan', 'password_awal', 'roles_saat_ini',
        ],
        'Pengurus' => ['ref_cabang', 'jabatan', 'identitas_user'],
    ];

    private const ORGANIZATION_FIELDS = [
        'ketua_pembina', 'anggota_pembina', 'ketua_pengawas',
        'anggota_pengawas', 'ketua_pengurus', 'sekretaris', 'bendahara',
    ];

    private const SINGLE_OFFICES = [
        'ketua_pembina', 'ketua_pengawas', 'ketua_pengurus',
    ];

    public function downloadTemplate(): BinaryFileResponse
    {
        return $this->download($this->newWorkbook(), 'template-cabang-pengguna-bwi.xlsx');
    }

    public function downloadExport(): BinaryFileResponse
    {
        $book = $this->newWorkbook();
        $branches = Cabang::query()->orderBy('id')->get();
        $usersByBranch = User::query()
            ->with('roles')
            ->whereIn('cabang_id', $branches->pluck('id'))
            ->orderBy('id')
            ->get()
            ->groupBy('cabang_id');

        $branchRow = 2;
        $userRow = 2;
        $officeRow = 2;

        foreach ($branches as $branch) {
            $reference = 'CB-' . $branch->id;
            $this->writeRow($book->getSheetByName('Cabang'), $branchRow++, [
                $reference, $branch->id, $branch->nama_cabang, $branch->lokasi, $branch->saldo_awal,
            ]);

            foreach ($usersByBranch->get($branch->id, collect()) as $user) {
                $this->writeRow($book->getSheetByName('Pengguna'), $userRow++, [
                    $reference, $user->id, $user->name, $user->email, $user->username,
                    $user->jenis_anggota, $user->no_hp, $user->nomor_ktp, $user->nomor_kk,
                    $user->alamat, $user->pekerjaan, $user->penghasilan_bulanan,
                    '', '', $user->getRoleNames()->implode(';'),
                ]);
            }

            foreach ($this->officeIds($branch) as $office => $ids) {
                foreach ($ids as $userId) {
                    $user = $usersByBranch->get($branch->id, collect())->firstWhere('id', $userId);
                    $identifier = $user
                        ? ($user->email ?: ($user->username ?: ($user->nomor_ktp ?: 'id:' . $user->id)))
                        : 'id:' . $userId;
                    $this->writeRow($book->getSheetByName('Pengurus'), $officeRow++, [
                        $reference, $office, $identifier,
                    ]);
                }
            }
        }

        return $this->download($book, 'export-cabang-pengguna-bwi-' . now()->format('Ymd-His') . '.xlsx');
    }

    /**
     * @return array<string, int>
     */
    public function import(string $path): array
    {
        if (! is_file($path) || filesize($path) > 10 * 1024 * 1024) {
            throw $this->error('File XLSX tidak ditemukan atau melebihi 10 MB.');
        }

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
        } catch (Throwable $exception) {
            throw $this->error('File tidak dapat dibaca. Gunakan template XLSX BWI.');
        }

        try {
            $branches = $this->readRows($book, 'Cabang');
            $users = $this->readRows($book, 'Pengguna');
            $offices = $this->readRows($book, 'Pengurus');
        } finally {
            $book->disconnectWorksheets();
            unset($book);
        }

        if ($branches === []) {
            throw $this->error('Sheet Cabang harus berisi minimal satu cabang.');
        }

        return DB::transaction(function () use ($branches, $users, $offices): array {
            $counts = [
                'cabang_baru' => 0,
                'cabang_diperbarui' => 0,
                'pengguna_baru' => 0,
                'pengguna_diperbarui' => 0,
                'kepengurusan_diperbarui' => 0,
            ];
            $branchByReference = [];
            $namesInFile = [];
            $branchIdsInFile = [];

            // Branch creation must happen before importing its users.
            foreach ($branches as $row) {
                $ref = $this->reference($row, 'Cabang');
                $name = trim($row['nama_cabang']);
                if ($name === '') {
                    throw $this->rowError('Cabang', $row, 'Nama cabang wajib diisi.');
                }
                if (isset($branchByReference[$ref])) {
                    throw $this->rowError('Cabang', $row, "Referensi cabang {$ref} berulang.");
                }
                $nameKey = mb_strtolower($name);
                if (isset($namesInFile[$nameKey])) {
                    throw $this->rowError('Cabang', $row, "Nama cabang {$name} berulang dalam file.");
                }
                $namesInFile[$nameKey] = true;

                $branch = null;
                if ($row['id_cabang'] !== '') {
                    $id = $this->positiveId($row['id_cabang'], 'Cabang', $row);
                    $branch = Cabang::query()->find($id);
                    if (! $branch) {
                        throw $this->rowError('Cabang', $row, "ID cabang {$id} tidak ditemukan.");
                    }
                } else {
                    $matches = Cabang::query()
                        ->whereRaw('LOWER(nama_cabang) = ?', [$nameKey])
                        ->limit(2)
                        ->get();
                    if ($matches->count() > 1) {
                        throw $this->rowError('Cabang', $row, 'Nama cabang ambigu. Gunakan id_cabang dari export.');
                    }
                    $branch = $matches->first();
                }

                if ($branch && isset($branchIdsInFile[$branch->id])) {
                    throw $this->rowError('Cabang', $row, 'Cabang yang sama muncul lebih dari sekali.');
                }

                $opening = $row['saldo_awal'];
                if ($branch) {
                    // NEVER correct opening capital via bulk import. The observer writes
                    // to the financial ledger when this attribute changes.
                    if ($opening !== '' && $this->money($opening, 'Cabang', $row) !== $this->money((string) $branch->saldo_awal, 'Cabang', $row)) {
                        throw $this->rowError('Cabang', $row, 'Saldo awal tidak boleh diubah melalui import. Gunakan prosedur koreksi keuangan.');
                    }
                    $branch->nama_cabang = $name;
                    if ($row['lokasi'] !== '') {
                        $branch->lokasi = $row['lokasi'];
                    }
                    if ($branch->isDirty()) {
                        $branch->save();
                        $counts['cabang_diperbarui']++;
                    }
                } else {
                    if ($row['lokasi'] === '' || $opening === '') {
                        throw $this->rowError('Cabang', $row, 'Cabang baru memerlukan lokasi dan saldo_awal (isi 0 jika tidak ada saldo).');
                    }
                    $branch = new Cabang();
                    $branch->forceFill([
                        'nama_cabang' => $name,
                        'lokasi' => $row['lokasi'],
                        'saldo_awal' => $this->money($opening, 'Cabang', $row),
                    ])->save();
                    $counts['cabang_baru']++;
                }

                $branchIdsInFile[$branch->id] = true;
                $branchByReference[$ref] = $branch;
            }

            $seenUserIds = [];
            foreach ($users as $row) {
                $ref = $this->reference($row, 'Pengguna');
                $branch = $branchByReference[$ref] ?? null;
                if (! $branch) {
                    throw $this->rowError('Pengguna', $row, "Referensi cabang {$ref} tidak ada di sheet Cabang.");
                }

                $user = $this->matchUser($row);
                if (! $user && trim($row['nama']) === '') {
                    throw $this->rowError('Pengguna', $row, 'Nama pengguna baru wajib diisi.');
                }
                if ($user && (int) $user->cabang_id !== (int) $branch->id) {
                    throw $this->rowError('Pengguna', $row, 'Akun sudah terhubung ke cabang lain. Perpindahan cabang tidak diizinkan melalui import.');
                }
                if ($user && isset($seenUserIds[$user->id])) {
                    throw $this->rowError('Pengguna', $row, 'Pengguna yang sama muncul lebih dari sekali.');
                }

                if ($row['jenis_anggota'] !== '' && ! in_array($row['jenis_anggota'], ['Anggota', 'Donatur'], true)) {
                    throw $this->rowError('Pengguna', $row, 'jenis_anggota hanya boleh Anggota atau Donatur.');
                }
                if ($row['password_awal'] !== '' && (strlen($row['password_awal']) < 12 || $user)) {
                    throw $this->rowError('Pengguna', $row, 'password_awal hanya untuk pengguna baru dan minimal 12 karakter.');
                }
                if ($row['email'] !== '' && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                    throw $this->rowError('Pengguna', $row, 'Format email tidak valid.');
                }
                foreach (['nomor_ktp', 'nomor_kk'] as $field) {
                    if ($row[$field] !== '' && ! preg_match('/^\d{16}$/', preg_replace('/\s+/', '', $row[$field]))) {
                        throw $this->rowError('Pengguna', $row, "{$field} harus 16 digit.");
                    }
                }

                $attributes = ['cabang_id' => $branch->id];
                $columns = [
                    'nama' => 'name', 'email' => 'email', 'username' => 'username',
                    'jenis_anggota' => 'jenis_anggota', 'no_hp' => 'no_hp',
                    'nomor_ktp' => 'nomor_ktp', 'nomor_kk' => 'nomor_kk',
                    'alamat' => 'alamat', 'pekerjaan' => 'pekerjaan',
                    'penghasilan_bulanan' => 'penghasilan_bulanan',
                ];
                foreach ($columns as $column => $field) {
                    if ($row[$column] !== '') {
                        $attributes[$field] = $column === 'penghasilan_bulanan'
                            ? $this->money($row[$column], 'Pengguna', $row)
                            : ($column === 'nomor_ktp' || $column === 'nomor_kk'
                                ? preg_replace('/\s+/', '', $row[$column])
                                : $row[$column]);
                    }
                }
                if ($row['password_awal'] !== '') {
                    $attributes['password'] = Hash::make($row['password_awal']);
                }

                if ($user) {
                    $user->forceFill($attributes);
                    if ($user->isDirty()) {
                        $user->save();
                        $counts['pengguna_diperbarui']++;
                    }
                } else {
                    $user = new User();
                    $user->forceFill($attributes)->save();
                    $counts['pengguna_baru']++;
                }
                $seenUserIds[$user->id] = true;

                if ($row['role_tambahan'] !== '') {
                    $roles = array_unique(array_map('trim', explode(';', $row['role_tambahan'])));
                    $allowed = array_unique(array_merge(['panel_user'], array_map(
                        fn ($field) => (string) config('bwi.' . $field),
                        self::ORGANIZATION_FIELDS,
                    )));
                    foreach ($roles as $role) {
                        if (! in_array($role, $allowed, true) || ! Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
                            throw $this->rowError('Pengguna', $row, "Role {$role} tidak dikenal atau tidak diizinkan untuk cabang.");
                        }
                    }
                    $user->assignRole($roles);
                }
            }

            // Update only branches mentioned in Pengurus. Other branches' office
            // structures are left untouched, including imports with empty sheet.
            $groups = [];
            foreach ($offices as $row) {
                $ref = $this->reference($row, 'Pengurus');
                $branch = $branchByReference[$ref] ?? null;
                if (! $branch) {
                    throw $this->rowError('Pengurus', $row, "Referensi cabang {$ref} tidak ada di sheet Cabang.");
                }
                $office = $row['jabatan'];
                if (! in_array($office, self::ORGANIZATION_FIELDS, true)) {
                    throw $this->rowError('Pengurus', $row, "Jabatan {$office} tidak dikenal.");
                }
                $user = $this->resolveOfficeUser($row['identitas_user'], $row);
                if ((int) $user->cabang_id !== (int) $branch->id) {
                    throw $this->rowError('Pengurus', $row, 'Pengurus harus berasal dari cabang yang sama.');
                }
                if (in_array($office, self::SINGLE_OFFICES, true) && ! empty($groups[$ref][$office])) {
                    throw $this->rowError('Pengurus', $row, "Jabatan {$office} hanya boleh satu orang.");
                }
                if (in_array($user->id, $groups[$ref][$office] ?? [], true)) {
                    throw $this->rowError('Pengurus', $row, 'Pengurus yang sama ditulis berulang pada jabatan yang sama.');
                }
                $groups[$ref][$office][] = $user->id;
            }

            foreach ($groups as $reference => $officesForBranch) {
                $branch = $branchByReference[$reference];
                $before = $this->officeIds($branch);
                $values = [];
                foreach (self::ORGANIZATION_FIELDS as $office) {
                    $ids = $officesForBranch[$office] ?? [];
                    $values[$office] = in_array($office, self::SINGLE_OFFICES, true)
                        ? ($ids[0] ?? null)
                        : array_map(fn ($id) => ['nama' => $id], $ids);
                }

                // Bypass only CabangObserver::updated for organizational fields:
                // the legacy observer detaches *all* roles when an officer changes.
                // Financial values are never modified here.
                Cabang::withoutEvents(function () use ($branch, $values): void {
                    $branch->forceFill($values);
                    if ($branch->isDirty()) {
                        $branch->save();
                    }
                });

                $after = $this->officeIds($branch);
                $this->syncOfficeRoles($before, $after);
                $counts['kepengurusan_diperbarui']++;
            }

            return $counts;
        }, 3);
    }

    private function matchUser(array $row): ?User
    {
        $candidates = collect();
        if ($row['id_user'] !== '') {
            $id = $this->positiveId($row['id_user'], 'Pengguna', $row);
            $user = User::query()->find($id);
            if (! $user) {
                throw $this->rowError('Pengguna', $row, "ID pengguna {$id} tidak ditemukan.");
            }
            $candidates->push($user);
        }
        foreach (['email', 'username'] as $field) {
            if ($row[$field] === '') {
                continue;
            }
            $matches = User::query()->where($field, $row[$field])->limit(2)->get();
            if ($matches->count() > 1) {
                throw $this->rowError('Pengguna', $row, "{$field} ambigu.");
            }
            if ($matches->isNotEmpty()) {
                $candidates->push($matches->first());
            }
        }
        if ($row['nomor_ktp'] !== '') {
            $nik = preg_replace('/\s+/', '', $row['nomor_ktp']);
            $matches = User::query()
                ->whereRaw("REPLACE(nomor_ktp, ' ', '') = ?", [$nik])
                ->limit(2)->get();
            if ($matches->count() > 1) {
                throw $this->rowError('Pengguna', $row, 'NIK ambigu. Gunakan id_user dari export.');
            }
            if ($matches->isNotEmpty()) {
                $candidates->push($matches->first());
            }
        }
        if ($candidates->unique('id')->count() > 1) {
            throw $this->rowError('Pengguna', $row, 'ID, email, username, atau NIK merujuk pada pengguna yang berbeda.');
        }
        if ($candidates->isEmpty() && $row['id_user'] === ''
            && $row['email'] === '' && $row['username'] === '' && $row['nomor_ktp'] === '') {
            throw $this->rowError('Pengguna', $row, 'Pengguna baru perlu email, username, atau NIK sebagai identitas unik.');
        }
        return $candidates->first();
    }

    private function resolveOfficeUser(string $identifier, array $row): User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            throw $this->rowError('Pengurus', $row, 'identitas_user wajib diisi.');
        }
        if (preg_match('/^id:(\d+)$/i', $identifier, $matches)) {
            $user = User::query()->find((int) $matches[1]);
            if ($user) {
                return $user;
            }
        } else {
            $matches = User::query()
                ->where('email', $identifier)
                ->orWhere('username', $identifier)
                ->orWhereRaw("REPLACE(nomor_ktp, ' ', '') = ?", [$identifier])
                ->limit(2)->get();
            if ($matches->count() === 1) {
                return $matches->first();
            }
        }
        throw $this->rowError('Pengurus', $row, "Pengguna {$identifier} tidak ditemukan atau ambigu.");
    }

    /**
     * @return array<string, list<int>>
     */
    private function officeIds(Cabang $branch): array
    {
        $result = [];
        foreach (self::ORGANIZATION_FIELDS as $field) {
            $value = $branch->{$field};
            if (in_array($field, self::SINGLE_OFFICES, true)) {
                $result[$field] = $value ? [(int) $value] : [];
            } else {
                $result[$field] = [];
                foreach (is_array($value) ? $value : [] as $member) {
                    if (! empty($member['nama'])) {
                        $result[$field][] = (int) $member['nama'];
                    }
                }
            }
        }
        return $result;
    }

    private function syncOfficeRoles(array $before, array $after): void
    {
        $beforeByUser = $this->rolesByUser($before);
        $afterByUser = $this->rolesByUser($after);
        $ids = array_unique(array_merge(array_keys($beforeByUser), array_keys($afterByUser)));

        foreach ($ids as $id) {
            $user = User::query()->findOrFail($id);
            $oldRoles = $beforeByUser[$id] ?? [];
            $newRoles = $afterByUser[$id] ?? [];

            foreach (array_diff($oldRoles, $newRoles) as $role) {
                $user->removeRole($role);
            }
            foreach (array_diff($newRoles, $oldRoles) as $role) {
                if (! Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
                    throw $this->error("Role {$role} belum ada di Filament Shield. Buat role lebih dahulu.");
                }
                $user->assignRole($role);
            }
            $user->is_organ = $newRoles !== [];
            $user->save();
        }
    }

    private function rolesByUser(array $officeIds): array
    {
        $result = [];
        foreach ($officeIds as $office => $ids) {
            $role = (string) config('bwi.' . $office);
            foreach ($ids as $id) {
                $result[$id][] = $role;
            }
        }
        return array_map('array_unique', $result);
    }

    private function readRows(Spreadsheet $book, string $name): array
    {
        $sheet = $book->getSheetByName($name);
        if (! $sheet) {
            throw $this->error("Sheet {$name} tidak ditemukan. Gunakan template resmi.");
        }

        $headers = self::HEADERS[$name];
        foreach ($headers as $i => $header) {
            $actual = trim((string) $sheet->getCell(Coordinate::stringFromColumnIndex($i + 1) . '1')->getValue());
            if ($actual !== $header) {
                throw $this->error("Header sheet {$name} kolom " . ($i + 1) . " harus {$header}.");
            }
        }

        $highestRow = $sheet->getHighestDataRow();
        if ($highestRow > 5001) {
            throw $this->error("Sheet {$name} melebihi batas 5.000 baris.");
        }
        $result = [];
        for ($i = 2; $i <= $highestRow; $i++) {
            $row = ['_row' => $i];
            $hasValues = false;
            foreach ($headers as $j => $header) {
                $value = $sheet->getCell(Coordinate::stringFromColumnIndex($j + 1) . $i)->getValue();
                if ($value !== null && ! is_scalar($value)) {
                    throw $this->error("Sheet {$name} baris {$i} berisi data tidak valid.");
                }
                $row[$header] = trim((string) ($value ?? ''));
                if (str_starts_with($row[$header], '=')) {
                    throw $this->error("Sheet {$name} baris {$i} tidak boleh berisi formula.");
                }
                if ($row[$header] !== '') {
                    $hasValues = true;
                }
            }
            if ($hasValues) {
                $result[] = $row;
            }
        }
        return $result;
    }

    private function reference(array $row, string $sheet): string
    {
        $ref = strtoupper(trim($row['ref_cabang']));
        if (! preg_match('/^[A-Z0-9_-]{1,40}$/', $ref)) {
            throw $this->rowError($sheet, $row, 'ref_cabang wajib, maksimal 40 karakter (huruf, angka, _ atau -).');
        }
        return $ref;
    }

    private function positiveId(string $value, string $sheet, array $row): int
    {
        if (! preg_match('/^[1-9]\d*$/', $value)) {
            throw $this->rowError($sheet, $row, 'ID harus bilangan bulat positif.');
        }
        return (int) $value;
    }

    private function money(string $value, string $sheet, array $row): string
    {
        if (! preg_match('/^\d{1,13}(\.\d{1,2})?$/', $value)) {
            throw $this->rowError($sheet, $row, 'Nominal harus angka positif tanpa Rp atau pemisah ribuan. Contoh: 1500000.');
        }
        return number_format((float) $value, 2, '.', '');
    }

    private function rowError(string $sheet, array $row, string $message): ValidationException
    {
        return $this->error("Sheet {$sheet} baris {$row['_row']}: {$message}");
    }

    private function error(string $message): ValidationException
    {
        return ValidationException::withMessages(['file' => $message]);
    }

    private function newWorkbook(): Spreadsheet
    {
        $book = new Spreadsheet();
        $first = true;

        foreach (self::HEADERS as $name => $headers) {
            $sheet = $first ? $book->getActiveSheet() : $book->createSheet();
            $first = false;
            $sheet->setTitle($name);
            $this->writeRow($sheet, 1, $headers);
            $sheet->freezePane('A2');
            $sheet->getSheetView()->setShowGridLines(false);
            $last = Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle("A1:{$last}1")->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB('193D69');
            $sheet->getRowDimension(1)->setRowHeight(28);
            $sheet->setAutoFilter("A1:{$last}1");
            foreach ($headers as $i => $header) {
                $column = Coordinate::stringFromColumnIndex($i + 1);
                $sheet->getColumnDimension($column)->setWidth(max(18, min(34, strlen($header) + 9)));
            }
            if ($name === 'Pengguna') {
                foreach (['G', 'H', 'I'] as $column) {
                    $sheet->getStyle("{$column}2:{$column}1000")->getNumberFormat()->setFormatCode('@');
                }
                $sheet->getStyle('N1')->getFill()->getStartColor()->setRGB('B35228');
                $sheet->getStyle('O1')->getFill()->getStartColor()->setRGB('64748B');
            }
        }

        $notes = $book->createSheet();
        $notes->setTitle('Panduan');
        $instructions = [
            ['TEMPLATE CABANG + PENGGUNA BWI', 'Satu berkas XLSX untuk cabang, user, dan kepengurusan.'],
            ['URUTAN', 'Isi Cabang, lalu Pengguna, lalu Pengurus. Semua diproses dalam satu transaksi.'],
            ['Cabang.ref_cabang', 'Kode penghubung dalam file, misalnya CB001. Tidak perlu ada di database.'],
            ['Cabang.id_cabang', 'Kosongkan untuk data baru. Untuk update, gunakan ID hasil export.'],
            ['Cabang.saldo_awal', 'Wajib saat membuat cabang, isi 0 jika kosong. DILARANG mengubah saldo awal cabang lama lewat import.'],
            ['Pengguna.id_user', 'Kosongkan untuk baru. Jika update, disarankan memakai ID dari export.'],
            ['Pengguna.identitas', 'Pengguna baru harus punya email, username, atau NIK unik agar import ulang tidak menggandakan.'],
            ['Pengguna.password_awal', 'Opsional, minimal 12 karakter, HANYA user baru. Kosong = belum bisa login dengan password. Jangan simpan file berisi password.'],
            ['Pengguna.role_tambahan', 'Opsional, pemisah titik koma (;). Hanya role cabang yang sudah ada di Shield. Tidak menghapus role lama.'],
            ['Pengguna.roles_saat_ini', 'Informasi hasil export, tidak akan dibaca saat import.'],
            ['Pengurus.jabatan', implode(', ', self::ORGANIZATION_FIELDS)],
            ['Pengurus.identitas_user', 'Isi email, username, NIK, atau id:123 untuk user existing. User harus berada pada cabang yang sama.'],
            ['Update kepengurusan', 'Jika ada baris Pengurus untuk cabang tertentu, daftar di sheet ini menggantikan kepengurusan cabang itu.'],
            ['Baris kosong', 'Diabaikan. Sel kosong saat update tidak menghapus data pengguna lama.'],
            ['Import ulang', 'Mencocokkan berdasarkan ID / identitas; tidak membuat saldo awal atau user ganda.'],
            ['KEAMANAN', 'Gunakan hanya oleh super_admin. Hapus file lokal berisi password setelah selesai.'],
        ];
        foreach ($instructions as $index => $row) {
            $this->writeRow($notes, $index + 1, $row);
        }
        $notes->getColumnDimension('A')->setWidth(32);
        $notes->getColumnDimension('B')->setWidth(110);
        $notes->getStyle('A1:B1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $notes->getStyle('A1:B1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('193D69');
        $notes->getSheetView()->setShowGridLines(false);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function writeRow($sheet, int $row, array $values): void
    {
        foreach (array_values($values) as $index => $value) {
            $cell = Coordinate::stringFromColumnIndex($index + 1) . $row;
            $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
        }
    }

    private function download(Spreadsheet $book, string $name): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'bwi-xlsx-');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
