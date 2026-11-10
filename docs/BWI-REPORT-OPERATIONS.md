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
