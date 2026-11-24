<?php

namespace App\Filament\Forms\Components;

use App\Services\BwiAttachment;
use Filament\Forms\Components\FileUpload;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class SecureAttachmentUpload extends FileUpload
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->disk('public')
            ->visibility('private')
            ->acceptedFileTypes(array_keys(BwiAttachment::MIME_EXTENSIONS))
            ->maxSize(10240)
            ->maxFiles(1)
            ->openable()
            ->downloadable()
            // Missing legacy files must not silently turn into null during edit.
            ->fetchFileInformation(false)
            ->getUploadedFileUsing(function (string $file): ?array {
                if (!$this->mayRetain($file) ||
                    !BwiAttachment::readableBy(auth()->user(), $file)) {
                    return null;
                }
                $info = BwiAttachment::file($file);
                return $info ? [
                    'name' => $file, 'size' => $info['size'],
                    'type' => $info['mime'], 'url' => BwiAttachment::url($file),
                ] : null;
            })
            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                $mime = $file->getMimeType();
                if (!$file->exists() || $file->getSize() > 10240 * 1024 ||
                    !is_string($mime) ||
                    !BwiAttachment::matchesExtension($file->getClientOriginalName(), $mime)) {
                    $this->invalidAttachment();
                }

                // Never use a client-provided filename or extension as a path.
                $name = bin2hex(random_bytes(20)).'.'.BwiAttachment::MIME_EXTENSIONS[$mime];
                $stored = $file->storeAs('', $name, ['disk' => 'public', 'visibility' => 'private']);

                if ($stored !== $name || BwiAttachment::file($name) === null) {
                    $this->invalidAttachment();
                }

                // Support another getState() or a retry after a failed DB
                // transaction without accepting arbitrary client-side strings.
                session()->put($this->pendingKey(), [
                    'name' => $name, 'expires' => now()->addMinutes(30)->timestamp,
                ]);
                return $name;
            });
    }

    private function pendingKey(): string
    {
        return 'bwi.pending_attachment.'.hash('sha256', implode('|', [
            auth()->id(), $this->getModel(), $this->getRecord()?->getKey() ?? 'new',
            $this->getName(),
        ]));
    }

    private function mayRetain(string $file): bool
    {
        $record = $this->getRecord();
        if ($record?->exists && $record->getRawOriginal($this->getName()) === $file) {
            return true;
        }

        $pending = session()->get($this->pendingKey(), []);
        return auth()->check() && BwiAttachment::validName($file) &&
            ($pending['name'] ?? null) === $file &&
            ($pending['expires'] ?? 0) >= now()->timestamp &&
            BwiAttachment::references($file)->isEmpty() &&
            BwiAttachment::file($file) !== null;
    }

    private function invalidAttachment(): never
    {
        throw ValidationException::withMessages([
            $this->getStatePath() => 'Lampiran tidak valid. Unggah PDF, JPG, atau PNG (maksimal 10 MB).',
        ]);
    }

    public function saveUploadedFiles(): void
    {
        $state = $this->getState() ?? [];
        if (!is_array($state) || count($state) > 1) {
            $this->invalidAttachment();
        }

        foreach ($state as $file) {
            if ($file instanceof TemporaryUploadedFile) {
                continue;
            }
            if (!is_string($file) || !$this->mayRetain($file)) {
                $this->invalidAttachment();
            }
        }

        parent::saveUploadedFiles();
    }
}
