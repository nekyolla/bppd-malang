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
