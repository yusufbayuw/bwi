<?php

namespace App\Services;

use App\Models\Berkas;
use App\Models\Cabang;
use App\Models\Cicilan;
use App\Models\Infak;
use App\Models\Laporan;
use App\Models\Pengeluaran;
use App\Models\Pinjaman;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class BwiAttachment
{
    // Each entry declares the real database column, Shield permission suffix
    // and branch column. General Berkas is an explicitly global resource.
    private const REFERENCES = [
        [User::class, 'file_ktp', 'user', 'cabang_id'],
        [User::class, 'file_kk', 'user', 'cabang_id'],
        [Cabang::class, 'berkas', 'cabang', 'id'],
        [Infak::class, 'berkas', 'infak', 'cabang_id'],
        [Pinjaman::class, 'berkas', 'pinjaman', 'cabang_id'],
        [Cicilan::class, 'berkas', 'cicilan', 'cabang_id'],
        [Pengeluaran::class, 'berkas', 'pengeluaran', 'cabang_id'],
        [Laporan::class, 'berkas', 'laporan', 'cabang_id'],
        [Berkas::class, 'file', 'berkas', null],
    ];

    public const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public static function validName(?string $name): bool
    {
        return is_string($name) &&
            preg_match('/\A[A-Za-z0-9][A-Za-z0-9_. -]{0,240}\.(?:pdf|jpe?g|png)\z/iD', $name) === 1 &&
            !str_contains($name, '..');
    }

    public static function matchesExtension(string $name, string $mime): bool
    {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        return isset(self::MIME_EXTENSIONS[$mime]) &&
            ($ext === self::MIME_EXTENSIONS[$mime] || ($mime === 'image/jpeg' && $ext === 'jpeg'));
    }

    public static function url(?string $name): ?string
    {
        return self::validName($name) ? route('bwi.attachments.show', ['filename' => $name]) : null;
    }

    public static function references(string $name): Collection
    {
        $matches = collect();
        foreach (self::REFERENCES as [$model, $column, $permission, $branch]) {
            foreach ($model::query()->where($column, $name)->get() as $record) {
                // MySQL's default collation may compare filenames case-insensitively.
                if ($record->getRawOriginal($column) === $name) {
                    $matches->push([$record, $permission, $branch]);
                }
            }
        }
        return $matches;
    }

    public static function readableBy(?User $user, string $name): bool
    {
        if (!$user || !self::validName($name)) {
            return false;
        }

        $matches = self::references($name);
        if ($matches->isEmpty()) {
            return false; // Never expose unreferenced or pending uploads.
        }

        // A filename shared by multiple records must not become an authorization
        // shortcut. Every referencing record must be within the actor's scope.
        return $matches->every(function (array $match) use ($user): bool {
            /** @var Model $record */
            [$record, $permission, $branch] = $match;
            $inScope = $branch === null ||
                $user->hasRole(config('bwi.adminAccess')) ||
                ((int) $user->cabang_id > 0 &&
                    (int) $record->getAttribute($branch) === (int) $user->cabang_id);

            return $inScope && (
                $user->can('view_'.$permission) ||
                $user->can('view_any_'.$permission)
            );
        });
    }

    public static function file(string $name): ?array
    {
        if (!self::validName($name)) {
            return null;
        }

        // The historical disk name stays public solely for data compatibility.
        // Resolve and check the physical path before any file read.
        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $path = $disk->path($name);
        $real = realpath($path);

        if ($root === false || $real === false || is_link($path) ||
            dirname($real) !== $root || !is_file($real) || !is_readable($real)) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($real);
        if (!is_string($mime) || !self::matchesExtension($name, $mime)) {
            return null;
        }

        return ['path' => $real, 'mime' => $mime, 'size' => filesize($real)];
    }

    public static function response(string $name): BinaryFileResponse
    {
        $file = self::file($name);
        abort_unless($file !== null, 404);

        $headers = [
            'Content-Type' => $file['mime'],
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'no-referrer',
            'X-Frame-Options' => 'SAMEORIGIN',
        ];
        // Preserve inline image previews; PDFs are downloaded rather than
        // interpreted as a same-origin document.
        $response = response()->file($file['path'], $headers);
        $response->setContentDisposition(
            $file['mime'] === 'application/pdf' ? 'attachment' : 'inline',
            $name
        );
        return $response;
    }
}
