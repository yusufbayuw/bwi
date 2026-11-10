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


## Arsip laporan bulanan dan kewenangan

- Laporan otomatis, termasuk arsip bulanan lama, tidak bisa dibuat, diedit, atau
  dihapus melalui Filament maupun penyimpanan Eloquent biasa.
- Pengguna tetap mengunduh laporan melalui tautan yang memeriksa identitas, izin,
  dan cabang. Hak atas file juga diperiksa terhadap lokasi PDF privat.
- Laporan non-otomatis tetap dapat diedit sesuai policy. Tindakan penghapusan
  banyak laporan sekaligus dinonaktifkan agar arsip tidak terhapus tanpa sengaja.
- Hanya service generator dan command migrasi yang secara eksplisit melakukan
  penulisan internal ke arsip melalui Laporan::withoutEvents().
- Operasi SQL manual/Query Builder yang tidak mengeksekusi model events berada
  di luar pengamanan ini. Batasi akses SQL production dan lakukan backup rutin.

## Verifikasi akhir sebelum menilai kesiapan merge/deploy

GitHub Actions pada PR memeriksa PHP lint, PHPUnit pada SQLite serta MySQL 8,
dan tes dua proses persetujuan pinjaman yang benar-benar bersaing pada cabang
sama melalui dua koneksi MySQL independen. Tes paralel harus mencatat tepat satu
approval, satu penolakan, satu pencairan, dan saldo yang tersisa.

**Audit histori yang sebenarnya belum dapat diwakili tes data sintetis.**
Sebelum GO-LIVE, pada salinan terbaru database production dengan data identitas
disamarkan jika diekspor, jalankan:

    php artisan bwi:audit-ledger --json > audit-ledger.json
    php artisan bwi:audit-ledger --as-of=2026-10-31

Audit tidak memperbaiki data, memutasi saldo, atau mencairkan pinjaman.
Jika ada anomali, lakukan investigasi untuk tiap cabang pada salinan database;
jangan membuat mutasi koreksi massal tanpa persetujuan akuntansi.

Migrasi file laporan bersifat operasi production yang terpisah dari merge kode.
Hanya jalankan bwi:secure-reports --execute setelah backup database dan
penyimpanan file diverifikasi. Pastikan web server/CDN tidak lagi menyajikan PDF
lama melalui URL publik.

**Catatan keamanan:** tests/Integration/mysql_parallel_approvals.php sengaja hanya
mengizinkan koneksi ke MySQL lokal bernama bwi_testing, dan digunakan setelah
migrate:fresh khusus database CI. Jangan pernah menjalankannya di server
operasional.
