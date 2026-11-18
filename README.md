# Sistem Informasi Pengembangan Kapasitas Pemerintahan Desa

Aplikasi pengelolaan pelatihan aparatur desa di Balai Besar Pengembangan Kapasitas Pemerintahan Desa (BBPD) Malang.

Alurnya:

1. Peserta mendaftar lewat form publik `/daftar`, tanpa akun.
2. Panitia memverifikasi pendaftaran, membagi kelas, dan menempatkan peserta di kamar asrama.
3. Panitia mencetak daftar hadir per hari. Presensi tetap ditulis di kertas.
4. Panitia mengunggah sertifikat PDF yang dibuat di luar sistem.
5. Peserta mengunduh sendiri sertifikatnya di `/sertifikat` dengan NIK.
6. Dashboard menampilkan statistik dan peta desa yang sudah mengikuti pelatihan.

Hanya panitia yang bisa login, di `/admin`:

- **superadmin:** mengelola akun, data master, data wilayah, dan audit log;
- **admin:** mengerjakan pelatihan sehari-hari.

## Teknologi

| | |
|---|---|
| Backend | Laravel 13, PHP 8.5 (minimal 8.5.12) |
| Panel admin | Filament 4, Livewire |
| Database | MySQL 8.4 |
| Tampilan | Tailwind CSS 4, Vite, Leaflet (peta) |
| Paket lain | spatie/laravel-permission (role), spatie/laravel-activitylog (audit log), barryvdh/laravel-dompdf (PDF), OpenSpout (Excel) |
| Test | Pest |
| Lingkungan lokal | Laravel Sail (Docker) |

## Menjalankan di komputer sendiri

Butuh Docker. Langkah pertama butuh PHP 8.5 dan Composer di komputer. Kalau tidak ada, ikuti bagian "Installing Composer Dependencies for Existing Applications" di dokumentasi Laravel Sail.

```bash
composer install
cp .env.example .env              # isi SUPERADMIN_PASSWORD
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Buka `http://localhost`. Sail melayani di port 80, bukan `:8000` seperti yang tertulis di `APP_URL`.

- `/daftar`: form registrasi.
- `/sertifikat`: unduh sertifikat.
- `/admin`: panel. Login dengan username dari `SUPERADMIN_USERNAME` dan password dari `SUPERADMIN_PASSWORD`.

Isi `--seed` bergantung pada lingkungan:

- **Seeder produksi:** role, akun superadmin, data wilayah seluruh Indonesia (`database/data/wilayah.csv`), status PTKP, dan sumber dana.
- **Lingkungan `local`:** `DemoSeeder` ikut jalan, berisi pelatihan, peserta, asrama, dan akun admin contoh (password `password`). Jangan jalankan `DemoSeeder` di server produksi.

Isian `.env` khusus aplikasi ini:

| Variabel | Kegunaan |
|---|---|
| `SUPERADMIN_USERNAME`, `SUPERADMIN_PASSWORD`, `SUPERADMIN_EMAIL` | Akun superadmin pertama, dibuat seeder. Tanpa password, akun tidak dibuat |
| `REGISTRASI_MAKS_PER_JAM` | Batas kiriman form registrasi per IP per jam (bawaan 5) |
| `SERTIFIKAT_PUBLIK_MAKS_GAGAL_PER_JAM`, `SERTIFIKAT_PUBLIK_MAKS_PER_JAM` | Batas pencarian di `/sertifikat` per IP per jam: pencarian yang tidak menemukan apa pun (30), dan semua pencarian (120) |
| `SERTIFIKAT_PUBLIK_MAKS_UNDUH_PER_JAM` | Batas unduhan sertifikat dari `/sertifikat` per IP per jam (120) |
| `SESSION_BATAS_DIAM`, `SESSION_BATAS_MUTLAK` | Panitia otomatis keluar setelah diam sekian menit (30), atau setelah login sekian menit (480) |
| `TRUSTED_PROXIES` | IP reverse proxy di depan aplikasi. Tanpa ini, semua pengunjung tampak ber-IP sama dan batas per IP berlaku untuk semua orang sekaligus |

## Perintah sehari-hari

```bash
./vendor/bin/sail artisan test                 # semua test
./vendor/bin/sail artisan test --filter=Asrama # sebagian
./vendor/bin/sail pint                         # rapikan format kode
./vendor/bin/sail php vendor/bin/phpstan analyse # analisis statis (Larastan)
./vendor/bin/sail npm run dev                  # Vite saat mengubah CSS/JS
./vendor/bin/sail artisan migrate:fresh --seed # ulang database dari nol
```

Sebelum commit, `pint`, `phpstan` (0 galat), dan seluruh test harus lolos.

## Struktur kode

| Folder | Isi |
|---|---|
| `app/Services/` | **Seluruh logika bisnis**: registrasi, verifikasi, kelas, asrama, sertifikat, export, statistik, akun. Panel dan halaman publik hanya memanggil service |
| `app/Enums/` | Status, peran, dan tipe. Jangan menulis status sebagai string |
| `app/Models/` | Model Eloquent. Setiap model menulis `protected $table` sendiri, karena nama tabel berbahasa Indonesia |
| `app/Policies/` | Hak akses, satu policy per model |
| `app/Filament/Admin/` | Panel `/admin`: resource, halaman, widget dashboard, aksi |
| `app/Filament/Admin/Resources/Pelatihan/Pages/` | Halaman kerja per pelatihan: Pendaftar, Kelas, Pembagian Kelas, Sertifikat. Di menu, halaman ini dibuka dari **Pendaftaran** |
| `app/Livewire/` | Halaman publik `Registrasi` (`/daftar`) dan `UnduhSertifikat` (`/sertifikat`) |
| `app/Http/Controllers/BerkasController.php` | Satu-satunya jalan membuka berkas pribadi (KTP, foto, surat tugas, sertifikat) |
| `app/Support/` | Pembantu: format tanggal, penyamaran NIK/nama, isian peserta, data instansi untuk kop surat |
| `resources/views/export/` | Template PDF daftar hadir |
| `resources/js/peta-wilayah.js`, `resources/geo/` | Peta dashboard dan data batas wilayah (sumber dan lisensi di `resources/geo/README.md`) |
| `database/data/wilayah.csv` | Kode dan nama wilayah Kemendagri |

## Istilah

| Istilah | Arti |
|---|---|
| Pelatihan | Satu penyelenggaraan, misalnya "Pelatihan Penyusunan RPJMDes 2026 Batch 1". Gabungan judul pelatihan, tahun anggaran, dan nomor batch |
| Peserta | Orang, diidentifikasi dengan NIK (unik). Tabel `peserta` |
| Pendaftaran | Satu peserta di satu pelatihan. Tabel `pelatihan_peserta`, model `PelatihanPeserta` |
| `data_isian` | Isian form registrasi yang disimpan apa adanya di pendaftaran. Diterapkan ke data peserta saat diverifikasi |
| Snapshot | Kolom `*_saat_pelatihan` di pendaftaran (jabatan, periode, desa, PTKP, waktu pelantikan): data peserta saat pelatihan itu, diisi saat verifikasi |
| Penggunaan kamar | Pemakaian satu kamar oleh satu pelatihan. Tipenya (L, P, atau pasutri) ditentukan penghuni pertama |
| Terlatih | Di kode (`desa_terlatih`, `peserta_terlatih`) berarti punya pendaftaran berstatus `selesai`. Di tampilan disebut "yang mengikuti" dan "Jumlah peserta" |

Status pelatihan: `draft → dibuka → berjalan → selesai`. Form registrasi hanya menampilkan pelatihan yang `dibuka`.

Status pendaftaran: `terdaftar → terverifikasi → selesai`, atau `batal`. Tidak ada status "ditolak". Saat pelatihan diubah ke `selesai`, semua peserta terverifikasi ikut `selesai`.

### Kode di komentar

| Kode | Arti |
|---|---|
| `README aturan N` | Aturan nomor N di bagian berikut |
| `PF-NN` | Nomor perubahan fitur yang diminta klien sesudah kebutuhan awal. Daftar lengkapnya ada di log perubahan fitur (paket serah terima). Perintah `git grep PF-14` menunjukkan kode yang terdampak |
| `FR-XXX-NN`, `NFR-NN`, `BR-NN` | Nomor kebutuhan di PRD (paket serah terima) |
| `PRD §`, `ARCHITECTURE §`, `DESIGN_SYSTEM §` | Bagian dokumen di paket serah terima. Kode tetap dapat dipahami tanpanya |

## Aturan yang tidak boleh dilanggar

1. **Berkas pribadi tidak pernah disimpan di disk `public`.** KTP, foto, surat tugas, dan sertifikat ada di `storage/app/private`.
   - Panitia membukanya lewat `BerkasController`, yang memeriksa policy.
   - Peserta mengunduh sertifikat lewat tautan bertanda tangan yang berlaku 15 menit.
2. **Data master tidak dihapus permanen.** Set `is_aktif = false`. Form baru hanya menampilkan data aktif.
3. **Status berubah hanya lewat service**, tidak pernah langsung dari form. Salah batal dikoreksi lewat `PendaftaranService::kembalikan`. Pendaftaran hanya boleh dihapus lewat `PendaftaranService::hapus`, dan hanya kalau belum pernah diverifikasi.
4. **Penempatan kamar selalu dalam `DB::transaction` dengan `lockForUpdate`** pada baris kamar. Yang diperiksa:
   - pelatihan diadakan di BBPD;
   - peserta sudah terverifikasi;
   - gender atau pasutri cocok;
   - kapasitas cukup;
   - kamar tidak dipakai pelatihan lain pada tanggal yang sama.
5. **Gender kamar tidak diisi admin.** Gender ditentukan oleh penghuni pertama. Pengecualiannya kamar pasutri: maksimal 2 orang, 1 laki-laki dan 1 perempuan.
6. **Snapshot diisi saat verifikasi** dan tidak diubah setelah pendaftaran `selesai`. Laporan dan statistik membaca snapshot, bukan profil peserta terkini.
7. **Presensi dan nilai tidak dicatat di sistem.** Jangan menambah tabel atau kolom untuk keduanya; sistem hanya mencetak daftar hadir. Membatalkan peserta melepas kamarnya, dan ditolak kalau peserta sudah punya sertifikat.
8. **Tidak ada penanda "pengganti".** Peserta yang tidak jadi ikut dibatalkan. Orang penggantinya mendaftar lewat form publik seperti peserta biasa.
9. **Kolom sensitif hanya untuk akun internal.** Kolom sensitif adalah NIK lengkap, NPWP, PTKP, dan berkas KTP.
   - NIK dan NPWP disamarkan di tabel dan daftar hadir. Pengecualian: export Excel data peserta memuat NIK lengkap.
   - Dashboard hanya memakai query agregat.
   - `/sertifikat` hanya menampilkan nama tersamar dan daftar sertifikat milik NIK yang diisi. Jangan menambah data pribadi lain di halaman itu.
10. **Hanya akun internal yang login** (role `superadmin` dan `admin`), dengan username, bukan email. Peserta tidak punya akun.
11. **Jangan menyimpan nilai turunan** (JP, jumlah hari, tahun menjabat, nama tampilan pelatihan). Hitung lewat accessor.
12. **Form registrasi tidak boleh menimpa data peserta lama.** Kalau NIK sudah terdaftar, form hanya menambah pendaftaran dan `data_isian`. Admin menerapkan perubahannya saat verifikasi. Form wajib punya batas per IP dan honeypot.

## Jebakan teknis

Hal-hal ini pernah menimbulkan bug. Baca sebelum mengubah bagian terkait.

**Service dan galat**
- **Galat service.**
  - Service menolak aturan bisnis dengan `$this->tolak('kolom', 'pesan')` dari trait `App\Services\Concerns\MenolakAturan`, yang melempar `ValidationException`.
  - Di form halaman, panggil service lewat `jalankanService()` (trait `App\Filament\Concerns\MemetakanGalatService`) agar pesan tampil di bawah isian.
  - Di modal aksi, pakai `GalatService::jalankan($action, 'Judul', fn () => ...)`: pesan tampil sebagai notifikasi dan modal tetap terbuka.
  - Jangan menulis blok try/catch notifikasi sendiri.
- **Kolom yang hanya diubah service** (status, snapshot, data verifikasi/pembatalan, kelas, tipe kamar) tidak ada di `$fillable`. Service memakai `forceFill`; factory boleh mengisinya.
- **Service menerima pelaku sebagai parameter** (`User $oleh`), bukan dari `auth()`.

**Cache dan statistik**
- **Cache hanya menyimpan array berisi nilai biasa.** Cache memakai driver database dengan `serializable_classes = false`, jadi objek seperti `Collection` kembali rusak. Test memakai cache `array`, sehingga masalah ini tidak tertangkap test.
- **Angka dashboard di-cache.** Model `Pelatihan` dan `PelatihanPeserta` menaikkan versi cache saat disimpan atau dihapus. Query statistik harus agregat.

**Filament dan Livewire**
- **Kelas Tailwind di view kustom panel admin dikompilasi lewat `resources/css/filament/admin/theme.css`.** Setelah menambah kelas baru, jalankan `npm run build`. Halaman publik memakai `resources/css/app.css`.
- **Komponen Livewire di luar `App\Livewire`** wajib didaftarkan dengan `Livewire::component()` (lihat `AdminPanelProvider::boot()`). Tanpa itu, render pertama jalan, tetapi setiap update dibalas 419. `Livewire::test()` tidak menangkapnya.
- **Middleware persisten di update Livewire:** respons yang dikembalikan diabaikan. Untuk menolak, lempar exception (lihat `BatasSesiAdmin`).
- **`validateOnly` tidak membawa pesan khusus isian Filament.** Kembalikan `$this->form->getValidationMessages()` dari `messages()` (lihat `Registrasi`).
- **Closure Filament diisi menurut nama parameter.** `fn ($n)` menerima `null`; pakai `$state`, `$record`, dan seterusnya.
- **`ActionGroup` tidak punya `authorize()`.** Pakai `visible()` di grup atau `authorize()` di tiap aksi.
- **`getRecord()`/`getOwnerRecord()` Filament hanya bertipe `Model`.** Di halaman per pelatihan pakai `$this->pelatihan()` (trait `MembacaPelatihan`), di closure beri tipe parameter (`fn (Pelatihan $record)`), dan di aksi massal tulis `@param Collection<int, PelatihanPeserta> $records` pada method bernama.
- **Galat `CheckboxList` berkunci `tanggal.0`**, bukan `tanggal`.
- **Unggahan di modal aksi memakai `FileUpload::storeFiles(false)`**, supaya service menerima `UploadedFile` dan menyimpannya sendiri ke disk privat.

**Validasi, PDF, dan export**
- **Urutan pesan validasi penting.** Pesan spesifik (`pelatihan_id.exists`) harus di atas pola `*.exists`, karena Laravel memakai pola pertama yang cocok.
- **PDF (dompdf):** pakai `page-break-before` untuk lembar kedua dan seterusnya. `page-break-after` di lembar terakhir menambah halaman kosong.

**Test**
- `TestCase` memanggil `withoutVite()`.
- Navigasi panel tersimpan selama satu proses test, jadi uji menu per role di file test terpisah.
- Setelah `callAction()` gagal validasi atau dihentikan, aksi tetap terbuka. Buat `Livewire::test()` baru untuk aksi berikutnya.
- Isi modal aksi tidak ada di `html()`; pakai `assertMountedActionModalSee()`.
- Pest mengevaluasi closure di dataset. Hindari closure bertingkat; pakai fungsi helper.

## Test

```bash
./vendor/bin/sail artisan test
```

- Test memakai database MySQL `testing` di container Sail (`phpunit.xml`).
- Setiap perubahan service wajib disertai test Pest.
- Pakai factory. Jangan bergantung pada data wilayah hasil seeder.
- Test ada di `tests/Feature` (service, panel admin di `Admin/`, halaman publik) dan `tests/Unit` (enum, format, data wilayah).
- **Analisis statis (Larastan, `phpstan.neon`, level 6):** menangkap salah tipe, properti/method yang tidak ada, dan kode mati sebelum dijalankan. Target 0 galat **tanpa baseline dan tanpa `@phpstan-ignore`**; perbaiki penyebabnya.
  - Properti yang dibuat "ajaib" (`$form` Livewire, properti `#[Computed]`, hasil `withCount`) didokumentasikan dengan `@property-read` di kelasnya.
  - Bentuk array yang dipakai lintas kelas ditulis sekali sebagai `@phpstan-type` (contoh `KeadaanKamar` di `AsramaService`) lalu diimpor dengan `@phpstan-import-type`.
  - Level 6 mewajibkan tipe di setiap parameter dan nilai kembali, termasuk isi kelas generik: scope ditulis `@param Builder<self> $query`, koleksi `@return Collection<int, Pelatihan>`, array `@param list<string> $kolom`.
  - Bila method induk dari package tidak bertipe (contoh `Login::rateLimit`), tipe parameter ditulis di PHPDoc saja; PHP melarang subclass mempersempit tipe parameter.
  - Langkah lanjutan bila sempat: level 7 (88 temuan saat ditulis, kebanyakan tipe union yang belum dipersempit, mis. `find()` bertipe model atau `Collection`, fungsi PHP yang bisa mengembalikan `false`).
- **Pemeriksaan otomatis (GitHub Actions):** `.github/workflows/test.yml` menjalankan `pint --test`, Larastan, dan seluruh test dengan MySQL 8.4 setiap push ke `main` dan setiap pull request. Bila repo dipindah dari GitHub, jalankan ketiga perintah itu secara manual sebelum menggabungkan perubahan.

## Konvensi

- Nama tabel, kolom, model, dan istilah domain berbahasa Indonesia. Nama method dan variabel teknis boleh berbahasa Inggris.
- Primary key selalu `id`. Kode wilayah bertipe `VARCHAR`, bukan integer.
- Teks antarmuka berbahasa Indonesia. Peserta disapa "Anda".
- Format tanggal memakai `App\Support\FormatTanggal`. Penyamaran NIK dan nama memakai `App\Support\Masking`.
- Data jabatan, kategori, judul pelatihan, asrama, dan kamar diisi panitia lewat panel, bukan seeder.

## Dokumen pendukung

Repo ini hanya berisi kode dan README. Dokumen lain diserahkan ke balai dalam paket serah terima:

- arsitektur (skema database dan alur teknis);
- dokumen kebutuhan (PRD, rujukan kode `FR-…`);
- log perubahan fitur (rujukan kode `PF-…`);
- design system;
- panduan deployment;
- hasil review kode.

Langkah memasang di server (kebutuhan server, `.env` produksi, HTTPS, backup, pembaruan) ada di panduan deployment di paket itu.
