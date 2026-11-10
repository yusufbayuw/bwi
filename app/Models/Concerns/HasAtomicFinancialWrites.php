<?php

namespace App\Models\Concerns;

use App\Models\Cabang;
use Illuminate\Validation\ValidationException;

/**
 * Keep the source model and its observer-created mutation rows in one
 * database transaction. Serialize financial writes per branch on the Cabang
 * row, which exists even before a branch has any mutation entries.
 *
 * Eloquent mass update/delete intentionally bypasses model events and must
 * never be used for financial records.
 */
trait HasAtomicFinancialWrites
{
    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            $this->lockFinancialScope();
            return parent::save($options);
        });
    }

    public function delete()
    {
        return $this->getConnection()->transaction(function () {
            $this->lockFinancialScope();
            return parent::delete();
        });
    }

    private function lockFinancialScope(): void
    {
        $branchIds = $this instanceof Cabang
            ? ($this->exists ? [(int) $this->getKey()] : [])
            : [(int) $this->getOriginal('cabang_id'), (int) $this->getAttribute('cabang_id')];

        $branchIds = array_values(array_unique(array_filter($branchIds)));
        sort($branchIds, SORT_NUMERIC);

        foreach ($branchIds as $branchId) {
            Cabang::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
        }

        if ($this->exists && count($this->getDirty()) > 0) {
            $current = $this->newQuery()->whereKey($this->getKey())->lockForUpdate()->first();
            if (!$current) {
                throw ValidationException::withMessages([
                    'id' => 'Transaksi sudah dihapus oleh proses lain. Silakan muat ulang halaman.',
                ]);
            }

            // Reject a stale edited row instead of calculating a correction
            // from values that changed in another browser/request.
            foreach (array_keys($this->getDirty()) as $field) {
                if ($this->getRawOriginal($field) !== $current->getRawOriginal($field)) {
                    throw ValidationException::withMessages([
                        $field => 'Data telah berubah oleh proses lain. Muat ulang sebelum menyimpan.',
                    ]);
                }
            }
        }
    }
}
