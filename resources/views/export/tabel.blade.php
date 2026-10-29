{{-- Daftar hadir per kelas per hari (ExportService), A4 tegak, satu halaman per lembar. Tanda tangan selang-seling dua sel. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $lembar[0]['judul'] ?? 'Daftar Hadir' }}</title>
    <style>
        @page { margin: 15mm 10mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .keterangan { margin: 0; font-size: 10px; }
        /* Pemisah sebelum lembar berikutnya: page-break-after di lembar terakhir memberi halaman kosong. */
        .lembar-berikutnya { page-break-before: always; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #555; padding: 4px 5px; vertical-align: top; }
        th { background: #e5e7eb; text-align: left; }
        th.ttd { text-align: center; }
        td.ttd { width: 25mm; height: 30px; }
        .no { width: 18px; text-align: right; }
        .lp { width: 18px; text-align: center; }
        .kaki { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8px; color: #555; }
    </style>
</head>
<body>
    @foreach ($lembar as $data)
        @php($awalTtd = count($data['kolom']) - $data['kolomTtd'])
        <div @class(['lembar-berikutnya' => ! $loop->first])>
            <h1>{{ $data['judul'] }}</h1>
            @foreach ($data['keterangan'] as $teks)
                <p class="keterangan">{{ $teks }}</p>
            @endforeach

            <table>
                <thead>
                    <tr>
                        @foreach (array_slice($data['kolom'], 0, $awalTtd) as $i => $nama)
                            <th @class(['no' => $i === 0, 'lp' => $nama === 'L/P'])>{{ $nama }}</th>
                        @endforeach
                        @if ($data['kolomTtd'] > 0)
                            <th class="ttd" colspan="{{ $data['kolomTtd'] }}">{{ $data['kolom'][$awalTtd] }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($data['baris'] as $isi)
                        <tr>
                            @foreach ($isi as $i => $nilai)
                                <td @class(['no' => $i === 0, 'lp' => ($data['kolom'][$i] ?? null) === 'L/P', 'ttd' => $i >= $awalTtd])>{{ $nilai }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($data['kolom']) }}">Belum ada peserta.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <p class="kaki">{{ \App\Support\Instansi::NAMA }} · Dicetak {{ $dicetak }}</p>
</body>
</html>
