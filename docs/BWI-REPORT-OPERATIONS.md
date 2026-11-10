# BWI — laporan otomatis dan migrasi PDF privat

Semua perubahan ini hanya di sisi server. Tampilan menu Laporan tetap Filament native.

## Persiapan deployment

1. Backup database **dan** direktori public/storage.
2. Deploy branch yang telah disetujui, lalu lakukan php artisan optimize:clear.
3. Tinjau daftar file PDF lama dengan php artisan bwi:secure-reports (dry-run, tidak mengubah apa pun).
4. Setelah backup diverifikasi, jalankan php artisan bwi:secure-reports --execute.
5. Jalankan dry-run kembali dan tangani laporan yang dilewati, termasuk yang tidak ditemukan. Periksa bahwa URL /storage/<nama-file-lama>.pdf tidak lagi dapat diakses.
6. Laporan baru berada di storage/app/bwi/reports/ dan diunduh melalui URL bertanda tangan sesi/autentikasi aplikasi (akses pengguna dan cabang diverifikasi server).
7. Jalankan php artisan bwi:audit-ledger (read-only) jika tersedia dan tindak lanjuti setiap anomali; jangan melakukan rewrite saldo historis secara otomatis.

**Catatan:** PDF yang pernah disimpan di luar storage/app/public atau public/storage mungkin masih berada di webroot, backup yang terbuka, atau CDN. Migrasi hanya membersihkan salinan yang direferensikan di tabel laporan. Periksa lokasi lain secara terpisah. Jangan menghapus file secara massal tanpa verifikasi.

## Penjadwalan

Di app/Console/Kernel.php terdapat dua task:
- tanggal 1 pukul 02:00 WIB: laporan bulan lalu;
- tanggal 1 pukul 02:30 WIB: laporan dua bulan lalu.

Pastikan mesin menggunakan cron setiap menit, satu entri untuk aplikasi ini:

    * * * * * cd /PATH/KE/BWI && /PATH/KE/PHP artisan schedule:run >> storage/logs/scheduler.log 2>&1

Lakukan php artisan schedule:list untuk memastikan task terdaftar.

## Eksekusi job

Pilihan sederhana: QUEUE_CONNECTION=sync (PDF diproses oleh proses schedule:run).
Untuk banyak cabang, gunakan queue database dan Supervisor yang menghidupkan queue:work;
buat dan migrasikan tabel jobs dahulu jika belum tersedia, dan periksa failed_jobs.

## Verifikasi

- Laporan PDF baru muncul pada menu Laporan tanpa tautan /storage publik.
- Cabang A tidak dapat membuka PDF cabang B, dan pengunjung tanpa login tidak dapat mengunduh.
- PDF pada periode lama tetap memperlihatkan piutang periode tersebut walaupun pinjaman sekarang berstatus Lunas.
- Audit dan rekonsiliasi saldo historis wajib dilakukan sebelum GO-LIVE.
- Tidak ada perubahan data historis otomatis pada deployment ini.


## Audit integritas ledger (read only)

    php artisan bwi:audit-ledger
    php artisan bwi:audit-ledger --cabang=1 --as-of=2026-10-31
    php artisan bwi:audit-ledger --json > audit-ledger.json

Kode hasil 0 berarti tidak ada anomali yang tercakup pemeriksaan. Kode hasil 1 berarti perlu investigasi.
Perintah ini mendeteksi ketidaksesuaian saldo vs debit/kredit, saldo negatif,
duplikasi posting cicilan/pinjaman, dan baris mutasi dengan lebih dari satu
referensi sumber. Ini bukan audit akuntansi menyeluruh; pemeriksaan tidak
mengubah data transaksi maupun menyimpulkan bahwa semua histori sudah benar.

## Proteksi transaksi bersamaan

Lima model sumber keuangan menggunakan transaksi database otomatis dan
mengunci baris Cabang dengan SELECT FOR UPDATE sebelum perubahan finansial
di Eloquent. Perubahan pada record yang sudah diubah proses lain ditolak
sebagai stale edit agar tidak terjadi koreksi saldo berulang.

Operasi update/delete massal langsung melalui Query Builder, SQL manual,
dan pemrosesan di luar Eloquent tidak mengeksekusi observer/trait. Jangan
gunakan jalur tersebut untuk perubahan keuangan.
