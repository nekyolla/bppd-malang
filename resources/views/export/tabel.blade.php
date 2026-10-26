{{-- Daftar hadir per kelas (ExportService), A4 tegak. Kolom tanda tangan per hari dibuat sempit. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        @page { margin: 15mm 10mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .keterangan { margin: 0; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #555; padding: 4px 5px; vertical-align: top; }
        th { background: #e5e7eb; text-align: left; }
        th.ttd { width: 15mm; text-align: center; }
        td.ttd { height: 30px; }
        .no { width: 18px; text-align: right; }
        .kaki { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8px; color: #555; }
    </style>
</head>
<body>
    <h1>{{ $judul }}</h1>
    @foreach ($keterangan as $teks)
        <p class="keterangan">{{ $teks }}</p>
    @endforeach

    <table>
        <thead>
            <tr>
                @foreach ($kolom as $i => $nama)
                    <th @class(['no' => $i === 0, 'ttd' => $i >= count($kolom) - $kolomTtd])>{{ $nama }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $isi)
                <tr>
                    @foreach ($isi as $i => $nilai)
                        <td @class(['no' => $i === 0, 'ttd' => $i >= count($kolom) - $kolomTtd])>{{ $nilai }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($kolom) }}">Belum ada peserta.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="kaki">BBPD Malang · Dicetak {{ $dicetak }}</p>
</body>
</html>
