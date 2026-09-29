import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import provinsi from '../geo/provinsi.json';

/*
 * Peta dashboard admin (PRD FR-DSB-03, DESIGN_SYSTEM §5.3).
 * Skala berurutan satu hue (biru primer): terang → gelap untuk sedikit → banyak
 * desa terlatih; di mode gelap arahnya dibalik. Provinsi di luar data wilayah
 * tampil kosong dengan garis putus-putus (bukan 0).
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

const ATRIBUSI =
    'Batas provinsi: <a href="https://github.com/denyherianto/indonesia-geojson-topojson-maps-with-38-provinces" target="_blank" rel="noopener">denyherianto</a>, <a href="https://creativecommons.org/licenses/by/4.0/" target="_blank" rel="noopener">CC BY 4.0</a> (diubah)';

const angka = (n) => new Intl.NumberFormat('id-ID').format(n);

window.petaProvinsi = ({ data, urlRincian }) => ({
    data,
    urlRincian,
    terpilih: null,
    // Disimpan sebagai state agar legenda ikut berganti warna saat tema berubah.
    modeAktif: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
    peta: null,
    lapisan: null,
    pengamat: null,
    pengamatUkuran: null,

    init() {
        this.$nextTick(() => this.gambar());

        // Filament mengganti kelas `dark` di <html> saat tema diubah.
        this.pengamat = new MutationObserver(() => {
            this.modeAktif = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            this.warnai();
        });
        this.pengamat.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
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

    gambar() {
        this.peta = L.map(this.$refs.peta, {
            zoomSnap: 0.25,
            scrollWheelZoom: false,
            attributionControl: true,
        });
        this.peta.attributionControl.setPrefix(false).addAttribution(ATRIBUSI);

        this.lapisan = L.geoJSON(provinsi, {
            style: (fitur) => this.gaya(fitur),
            onEachFeature: (fitur, layer) => {
                const d = this.data[fitur.properties.kode];

                layer.bindTooltip(
                    d
                        ? `<strong>${fitur.properties.nama}</strong><br>${angka(d.desa_terlatih)} desa terlatih`
                        : `<strong>${fitur.properties.nama}</strong><br>Di luar wilayah kerja`,
                    { sticky: true, direction: 'top' },
                );

                layer.on({
                    mouseover: () => layer.setStyle({ weight: 2.5, color: WARNA[this.mode()].sorot }),
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
