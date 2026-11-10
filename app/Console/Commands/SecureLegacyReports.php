<?php

namespace App\Console\Commands;

use App\Models\Laporan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class SecureLegacyReports extends Command
{
    protected $signature = 'bwi:secure-reports {--execute : Copy and verify report files, then remove public copies}';

    protected $description = 'Safely move existing BWI PDF reports out of publicly accessible storage';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $this->info($execute
            ? 'Migrasi laporan lama: EXECUTE (menghapus salinan publik setelah diverifikasi).'
            : 'Migrasi laporan lama: DRY RUN (tidak mengubah apa pun).');

        $migrated = 0;
        $skipped = 0;
        $records = Laporan::query()
            ->where('berkas', 'not like', 'bwi/reports/%')
            ->orderBy('id')->get();

        foreach ($records as $record) {
            $name = (string) $record->berkas;
            if (!$this->isSafeLegacyName($name)) {
                $this->warn("Laporan #{$record->id}: nama file tidak valid, perlu penanganan manual.");
                $skipped++;
                continue;
            }

            $source = public_path('storage'.DIRECTORY_SEPARATOR.$name);
            if (!is_file($source)) {
                // Servers without the storage:link symlink may keep their
                // original PDFs directly on the configured public disk.
                $source = Storage::disk('public')->path($name);
            }

            if (!is_file($source)) {
                $this->warn("Laporan #{$record->id}: PDF tidak ditemukan, lewati.");
                $skipped++;
                continue;
            }

            $target = "bwi/reports/legacy/{$record->id}.pdf";
            $this->line("Laporan #{$record->id}: {$name} -> {$target}");

            if (!$execute) {
                $migrated++;
                continue;
            }

            try {
                $originalHash = hash_file('sha256', $source);
                $disk = Storage::disk('local');
                if (!$disk->exists($target)) {
                    if (!$disk->put($target, file_get_contents($source))) {
                        throw new RuntimeException('Penulisan ke storage privat gagal.');
                    }
                }

                if (hash_file('sha256', $disk->path($target)) !== $originalHash) {
                    throw new RuntimeException('Hash PDF sumber dan tujuan tidak sama.');
                }

                DB::transaction(function () use ($record, $name, $target) {
                    $fresh = Laporan::query()->lockForUpdate()->findOrFail($record->id);
                    if ($fresh->berkas !== $name) {
                        throw new RuntimeException('Referensi laporan berubah selama migrasi.');
                    }

                    $fresh->berkas = $target;
                    $fresh->save();
                });

                // Keep an old shared filename while another report still uses it.
                if (!Laporan::where('berkas', $name)->exists() && is_file($source)) {
                    if (!@unlink($source)) {
                        // Keep the original reference for a repeatable retry
                        // rather than silently leaving a public orphan.
                        DB::transaction(function () use ($record, $name, $target) {
                            $fresh = Laporan::query()->lockForUpdate()->findOrFail($record->id);
                            if ($fresh->berkas === $target) {
                                $fresh->berkas = $name;
                                $fresh->save();
                            }
                        });
                        throw new RuntimeException('Penghapusan salinan publik gagal; referensi dikembalikan untuk percobaan ulang.');
                    }
                }

                $migrated++;
            } catch (\Throwable $exception) {
                $this->error("Laporan #{$record->id}: {$exception->getMessage()}");
                $skipped++;
            }
        }

        $this->info("Selesai: ".($execute ? 'migrasi' : 'calon migrasi')." {$migrated}, perlu pemeriksaan {$skipped}.");
        return $skipped === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function isSafeLegacyName(string $name): bool
    {
        return $name !== ''
            && !str_contains($name, '/')
            && !str_contains($name, '\\')
            && preg_match('/\.pdf$/i', $name) === 1;
    }
}
