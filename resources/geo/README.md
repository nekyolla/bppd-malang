# Data geografis

## `provinsi.json`

Batas 38 provinsi Indonesia untuk peta dashboard admin (PRD FR-DSB-03).

| | |
|---|---|
| **Sumber** | [denyherianto/indonesia-geojson-topojson-maps-with-38-provinces](https://github.com/denyherianto/indonesia-geojson-topojson-maps-with-38-provinces), berkas `GeoJSON/indonesia-38-provinces.geojson` (v1.0.0) |
| **Diunduh** | 29 September 2026 |
| **SHA-256 berkas asli** | `97f23f738c7aa66836b971d76381c009a34c6aaefe9c982e85f060e678402027` |
| **Lisensi data** | [Creative Commons Attribution 4.0 International (CC BY 4.0)](https://creativecommons.org/licenses/by/4.0/) — atribusi wajib, ditampilkan di pojok peta |
| **Catatan sumber** | `DATA_LICENSE.md` merujuk `SOURCE.md` untuk asal data hulu, tetapi berkas itu tidak ada di repo sumber. Geometri sudah disederhanakan oleh pembuatnya; cukup untuk peta tematik tingkat nasional, bukan untuk keperluan batas wilayah resmi. |

### Perubahan dari berkas asli

1. **Kode provinsi Papua** diganti ke kode Kemendagri (sama dengan `database/data/wilayah.csv`), karena berkas asli masih memakai kode provinsi induk:

   | Provinsi | Kode asli | Kode di sini |
   |---|---|---|
   | Papua | 91 | 91 |
   | Papua Barat | 92 | 92 |
   | Papua Selatan | 91 | 93 |
   | Papua Tengah | 91 | 94 |
   | Papua Pegunungan | 91 | 95 |
   | Papua Barat Daya | 92 | 96 |

2. Properti `KODE_PROV`/`PROVINSI` diganti menjadi `kode`/`nama`; properti lain dan `id` dibuang.
3. Koordinat dibulatkan ke 3 angka desimal (±110 m) dan berkas diurutkan menurut kode.

Setiap `kode` unik dan dicocokkan dengan `provinsi.kode`. Jika data wilayah menambah provinsi, periksa ulang kecocokan kode dan nama.

## `kab-kota/{kode provinsi}.json`

Batas kab/kota per provinsi untuk peta di halaman Rincian Wilayah, hanya untuk 18 provinsi di `database/data/wilayah.csv`. Dimuat per provinsi (chunk terpisah Vite) saat dibutuhkan.

| | |
|---|---|
| **Sumber** | [ardian28/GeoJson-Indonesia-38-Provinsi](https://github.com/ardian28/GeoJson-Indonesia-38-Provinsi), berkas `Kabupaten/38 Provinsi Indonesia - Kabupaten.json` |
| **Asal data hulu** | Badan Informasi Geospasial (geoservice.big.go.id), batas desa/kelurahan 2022 yang digabung per kab/kota oleh pembuat repo |
| **Diunduh** | 29 September 2026 |
| **SHA-256 berkas asli** | `ebf19ce23c0b5894e7f29e99be39f5aaadb50ef6c405098c3cf17887d2b87f9e` |
| **Lisensi** | MIT (repo sumber). Atribusi ke BIG dan repo sumber ditampilkan di pojok peta |

### Cara pencocokan dengan `kab_kota`

1. Provinsi selain Papua: dicocokkan lewat **kode** (`KDPKAB` = `kab_kota.kode`), karena kodenya sudah kode Kemendagri.
2. Enam provinsi Papua: dicocokkan lewat **nama provinsi + nama kab/kota** (dinormalisasi: tanpa awalan "Kab.", huruf kecil, tanpa spasi/tanda hubung), karena `KDPKAB` di sumber masih kode sebelum pemekaran (mis. Merauke `91.01`, di sini `93.01`).
3. Beberapa potongan dengan kode yang sama digabung menjadi satu `MultiPolygon` (mis. potongan "Pahuwato", salah ketik dari Pohuwato, masuk ke Kab. Pohuwato).
4. Semua 200 kab/kota di data wilayah mendapat geometri. Fitur sumber yang tidak punya pasangan (22 kota yang hanya berisi kelurahan) tetap disimpan dengan `kode: null` dan `keterangan` agar peta provinsi utuh; digambar kosong.
5. Properti hanya `kode`, `nama`, dan `keterangan` (untuk fitur tanpa pasangan); koordinat dibulatkan ke 3 angka desimal.

### Keterbatasan

- Empat fitur sumber tidak punya geometri sehingga tidak tergambar: Kota Mojokerto (tampil sebagai lubang di tengah Kab. Mojokerto), potongan "Pahuwato", serta dua wilayah perbatasan yang belum ditetapkan ("Sumbawa/Sumbawa Barat", "Minahasa Selatan/Bolaang Mongondow Timur").
- Geometri sudah sangat disederhanakan oleh pembuat repo; cukup untuk peta tematik, bukan batas resmi.
- Jika data wilayah berubah (kab/kota baru, pemekaran), ulangi pencocokan dan pastikan setiap kab/kota tetap punya geometri.
