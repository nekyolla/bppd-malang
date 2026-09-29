import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import provinsi from '../geo/provinsi.json';

/*
 * Peta wilayah untuk dashboard dan halaman Rincian Wilayah (PRD FR-DSB-03/04,
 * DESIGN_SYSTEM §5.3). Skala berurutan satu hue (biru primer): terang → gelap
 * untuk sedikit → banyak desa terlatih; di mode gelap arahnya dibalik. Wilayah
 * tanpa data tampil kosong dengan garis putus-putus (berbeda dari 0).
 */
const WARNA = {
    light: {
        skala: ['#dbeafe', '#93c5fd', '#3b82f6', '#1d4ed8'], // blue-100, 300, 500, 700
        latar: '#f8fafc',
        garis: '#ffffff',
        garisKosong: '#94a3b8',
        sorot: '#0f172a',
    },
    dark: {
        skala: ['#1e3a8a', '#1d4ed8', '#60a5fa', '#bfdbfe'], // blue-900, 700, 400, 200
        latar: '#0f172a',
        garis: '#0f172a',
        garisKosong: '#64748b',
        sorot: '#f8fafc',
    },
};

const ATRIBUSI = {
    provinsi:
        'Batas provinsi: <a href="https://github.com/denyherianto/indonesia-geojson-topojson-maps-with-38-provinces" target="_blank" rel="noopener">denyherianto</a>, <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a> (diubah)',
    kabKota:
        'Batas kab/kota: BIG via <a href="https://github.com/ardian28/GeoJson-Indonesia-38-Provinsi" target="_blank" rel="noopener">ardian28</a> (MIT, diubah)',
};

// Satu chunk per provinsi, hanya diunduh saat provinsi itu dibuka.
const KAB_KOTA = import.meta.glob('../geo/kab-kota/*.json', { import: 'default' });

const angka = (n) => new Intl.NumberFormat('id-ID').format(n);

async function muatGeo(geo) {
    if (geo === 'provinsi') return provinsi;

    const muat = KAB_KOTA[`../geo/kab-kota/${geo.replace('kab-kota/', '')}.json`];

    return muat ? muat() : { type: 'FeatureCollection', features: [] };
}

/**
 * @param {object} opsi
 * @param {object} opsi.data        Statistik per kode wilayah: desa_terlatih, url, dll.
 * @param {string} opsi.geo         'provinsi' atau 'kab-kota/{kode provinsi}'.
 * @param {'panel'|'buka'} opsi.klik 'panel' menampilkan rincian di samping peta; 'buka' pindah ke url wilayah.
 * @param {string} opsi.labelKosong  Keterangan wilayah tanpa data di legenda & tooltip.
 */
window.petaWilayah = ({ data, geo = 'provinsi', klik = 'panel', labelKosong = 'Di luar wilayah kerja' }) => ({
    data,
    klik,
    labelKosong,
    terpilih: null,
    modeAktif: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
    memuat: true,
    peta: null,
    lapisan: null,
    pengamat: null,
    pengamatUkuran: null,

    async init() {
        // Filament mengganti kelas `dark` di <html> saat tema diubah.
        this.pengamat = new MutationObserver(() => {
            this.modeAktif = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            this.warnai();
        });
        this.pengamat.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        const fitur = await muatGeo(geo);
        this.memuat = false;
        await this.$nextTick();
        this.gambar(fitur);
    },

    destroy() {
        this.pengamat?.disconnect();
        this.pengamatUkuran?.disconnect();
        this.peta?.remove();
    },

    mode() {
        return this.modeAktif;
    },

    /** Batas kelas: 0, lalu tiga rentang sama besar sampai nilai tertinggi. */
    ambang() {
        const maks = Math.max(0, ...Object.values(this.data).map((d) => d.desa_terlatih));

        return [Math.ceil(maks / 3), Math.ceil((2 * maks) / 3), maks];
    },

    kelas(nilai) {
        if (nilai === 0) return 0;

        const [a, b] = this.ambang();

        return nilai <= a ? 1 : nilai <= b ? 2 : 3;
    },

    gaya(fitur) {
        const w = WARNA[this.mode()];
        const d = this.data[fitur.properties.kode];
        const dipilih = this.terpilih?.kode === fitur.properties.kode;

        if (!d) {
            return { fillColor: w.latar, fillOpacity: 1, color: w.garisKosong, weight: 1, dashArray: '3 3' };
        }

        return {
            fillColor: w.skala[this.kelas(d.desa_terlatih)],
            fillOpacity: 1,
            color: dipilih ? w.sorot : w.garis,
            weight: dipilih ? 2.5 : 1,
            dashArray: null,
        };
    },

    gambar(geojson) {
        this.peta = L.map(this.$refs.peta, {
            zoomSnap: 0.25,
            scrollWheelZoom: false,
            attributionControl: true,
        });
        this.peta.attributionControl
            .setPrefix(false)
            .addAttribution(geo === 'provinsi' ? ATRIBUSI.provinsi : ATRIBUSI.kabKota);

        this.lapisan = L.geoJSON(geojson, {
            style: (fitur) => this.gaya(fitur),
            onEachFeature: (fitur, layer) => {
                const { kode, nama, keterangan } = fitur.properties;
                const d = this.data[kode];

                layer.bindTooltip(
                    d
                        ? `<strong>${nama}</strong><br>${angka(d.desa_terlatih)} desa terlatih`
                        : `<strong>${nama}</strong><br>${keterangan ?? this.labelKosong}`,
                    { sticky: true, direction: 'top' },
                );

                layer.on({
                    mouseover: () => d && layer.setStyle({ weight: 2.5, color: WARNA[this.mode()].sorot }),
                    mouseout: () => this.lapisan.resetStyle(layer),
                    click: () => this.pilih(fitur.properties),
                });
            },
        }).addTo(this.peta);

        this.pasKan();
        this.warnai();

        // Sesuaikan ulang saat lebar wadah berubah (sidebar dibuka/ditutup, ukuran jendela).
        this.pengamatUkuran = new ResizeObserver(() => this.pasKan());
        this.pengamatUkuran.observe(this.$refs.peta);
    },

    pasKan() {
        if (!this.lapisan?.getLayers().length) return;

        this.peta.invalidateSize();
        this.peta.fitBounds(this.lapisan.getBounds(), { padding: [8, 8] });
    },

    warnai() {
        if (!this.lapisan) return;

        this.$refs.peta.style.background = WARNA[this.mode()].latar;
        this.lapisan.setStyle((fitur) => this.gaya(fitur));
    },

    pilih({ kode, nama }) {
        const d = this.data[kode];

        if (this.klik === 'buka') {
            if (d?.url) window.location.href = d.url;

            return;
        }

        this.terpilih = d ? { ...d, kode } : { kode, nama, kosong: true };
        this.warnai();
    },

    get legenda() {
        const w = WARNA[this.mode()];
        const [a, b, maks] = this.ambang();
        const rentang = [[0, 0], [1, a], [a + 1, b], [b + 1, maks]];

        return w.skala
            .map((warna, i) => {
                const [bawah, atas] = rentang[i];

                return { warna, label: bawah === atas ? angka(bawah) : `${angka(bawah)}–${angka(atas)}`, ada: i === 0 || (maks > 0 && bawah <= atas) };
            })
            .filter((item) => item.ada);
    },

    get warnaKosong() {
        return WARNA[this.mode()];
    },

    angka,
});
