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
            ->stream('Laporan-'.$month->format('Y-m').'.pdf');
    }

    public function download(Request $request, Laporan $laporan)
    {
        $this->authorizeBranch($request, (int) $laporan->cabang_id);
        $path = (string) $laporan->berkas;

        if (preg_match('#^bwi/reports/(?:cabang-[0-9]+|legacy)/[A-Za-z0-9_.-]+\.pdf$#i', $path)) {
            abort_unless(Storage::disk('local')->exists($path), 404);
            return Storage::disk('local')->response($path, basename($path), [
                'Content-Type' => 'application/pdf',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        // Transitional fallback only: deploy the bwi:secure-reports command
        // to eliminate public URL access to legacy reports.
        abort_unless(
            preg_match('/^[^\/\\\\]+\.pdf$/i', $path) && $path !== '.' && $path !== '..',
            404
        );
        $legacy = public_path('storage'.DIRECTORY_SEPARATOR.$path);
        abort_unless(is_file($legacy), 404);
        return response()->file($legacy, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
