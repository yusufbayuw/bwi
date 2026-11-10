<?php

/**
 * Runs in the disposable CI MySQL database only.
 * NEVER run this against a live/production database.
 */
declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Cabang;
use App\Models\Mutasi;
use App\Models\Pinjaman;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$connection = config('database.default');
$database = config('database.connections.mysql.database');
$host = config('database.connections.mysql.host');

if ($connection !== 'mysql' || $database !== 'bwi_testing' || ! in_array($host, ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "REFUSED: parallel test is restricted to localhost/bwi_testing MySQL.\n");
    exit(2);
}

if (! function_exists('pcntl_fork') || ! function_exists('pcntl_waitpid')) {
    fwrite(STDERR, "Missing pcntl: real parallel execution cannot be verified.\n");
    exit(2);
}

$branch = Cabang::create([
    'nama_cabang' => 'Parallel Approval CI',
    'saldo_awal' => '1000000',
]);
$ids = [];

for ($group = 0; $group < 2; $group++) {
    $members = [];
    for ($i = 0; $i < 5; $i++) {
        $member = User::factory()->create();
        $member->forceFill(['cabang_id' => $branch->id, 'is_kelompok' => false])->save();
        $members[] = ['user_id' => $member->id];
    }

    $loan = Pinjaman::create([
        'cabang_id' => $branch->id,
        'nama_kelompok' => 'Parallel '.($group + 1),
        'status' => 'Menunggu Verifikasi',
        'acc_pinjaman' => 0,
        'list_anggota' => $members,
        'jumlah_anggota' => 5,
        'total_pinjaman' => '600000',
        'lama_cicilan' => 5,
        'tanggal_cicilan_pertama' => '2026-12-01',
    ]);
    $ids[] = $loan->id;
}

DB::disconnect('mysql');

$childPids = [];
$resultFiles = [];

foreach ($ids as $id) {
    $file = tempnam(sys_get_temp_dir(), 'bwi-ci-parallel-');
    if ($file === false) {
        fwrite(STDERR, "Unable to create result file.\n");
        exit(2);
    }
    $resultFiles[$id] = $file;
    $pid = pcntl_fork();

    if ($pid === -1) {
        fwrite(STDERR, "Unable to fork child process.\n");
        exit(2);
    }

    if ($pid === 0) {
        // Separate PDO connection after fork: real competing SQL transactions.
        DB::purge('mysql');

        try {
            Pinjaman::findOrFail($id)->update([
                'acc_pinjaman' => 1,
                'status' => 'Cicilan Berjalan',
            ]);
            file_put_contents($file, 'approved');
        } catch (ValidationException $error) {
            file_put_contents($file, 'rejected');
        } catch (Throwable $error) {
            file_put_contents($file, 'error: '.$error->getMessage());
        }

        exit(0);
    }

    $childPids[] = $pid;
}

foreach ($childPids as $pid) {
    pcntl_waitpid($pid, $status);
    if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
        fwrite(STDERR, "Child process did not exit cleanly.\n");
        exit(1);
    }
}

DB::purge('mysql');

$results = array_map(fn ($path) => trim((string) file_get_contents($path)), array_values($resultFiles));
foreach ($resultFiles as $path) {
    @unlink($path);
}

$approved = Pinjaman::whereIn('id', $ids)->where('acc_pinjaman', 1)->count();
$disbursements = Mutasi::whereIn('pinjaman_id', $ids)->count();
$cash = (float) Mutasi::where('cabang_id', $branch->id)->orderByDesc('id')->value('saldo_umum');

if (
    count(array_filter($results, fn ($r) => $r === 'approved')) !== 1
    || count(array_filter($results, fn ($r) => $r === 'rejected')) !== 1
    || $approved !== 1
    || $disbursements !== 1
    || abs($cash - 400000) > 0.01
) {
    fwrite(STDERR, 'Parallel approval FAIL: '.json_encode(compact('results', 'approved', 'disbursements', 'cash'))."\n");
    exit(1);
}

fwrite(STDOUT, "Parallel approval PASS: one approval, one rejection, one disbursement, correct cash balance.\n");
