<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Models\Laporan;
use App\Services\BwiMonthlyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LaporanBulananCabangController extends Controller
{
    private function authorizeBranch(Request $request, int $branchId): void
    {
        $user = $request->user();
        abort_unless($user && (
            $user->hasRole(config('bwi.adminAccess'))
            || (int) $user->cabang_id === $branchId
        ), 403);
        abort_unless(
            $user->can('view_any_laporan') || $user->can('view_laporan'),
            403
        );
    }

    public function laporanBulananCabang(
        Request $request,
        int $cabang_id,
        string $jenis,
        BwiMonthlyReport $reports
    ) {
        abort_unless(strtolower($jenis) === 'bulanan', 404);
        $branch = Cabang::findOrFail($cabang_id);
        $this->authorizeBranch($request, (int) $branch->id);

        $month = CarbonImmutable::now('Asia/Jakarta')->startOfMonth();
        return Pdf::loadView('laporan.bulanan.cabang', $reports->viewData($branch, $month))
            ->stream('Laporan-'.$month->format('Y-m').'.pdf')
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    public function download(Request $request, Laporan $laporan)
    {
        $this->authorizeBranch($request, (int) $laporan->cabang_id);
        $path = (string) $laporan->berkas;

        if (preg_match('#^bwi/reports/(?:cabang-[0-9]+|legacy)/[A-Za-z0-9_.-]+\.pdf$#i', $path)) {
            // A report row must not be repointed to another branch's PDF.
            $ownedByBranch = str_starts_with($path, 'bwi/reports/cabang-'.$laporan->cabang_id.'/');
            $ownedLegacy = $path === 'bwi/reports/legacy/'.$laporan->id.'.pdf';
            abort_unless($ownedByBranch || $ownedLegacy, 403);
            abort_unless(Storage::disk('local')->exists($path), 404);
            return Storage::disk('local')->response($path, basename($path), [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Old filenames remain on the compatibility disk, not in the webroot.
        // Check all references, including identity/transaction attachments.
        abort_unless(\App\Services\BwiAttachment::readableBy($request->user(), $path), 403);
        return \App\Services\BwiAttachment::response($path);
    }
}
