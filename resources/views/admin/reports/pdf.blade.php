<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Absensi Murid</title>
    <style>
        @page { margin: 34pt 37pt 46pt 37pt; }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #000;
            margin: 0;
        }

        /* Kop surat */
        table.kop { width: 100%; border-collapse: collapse; }
        table.kop td { padding: 0; text-align: center; vertical-align: middle; }
        table.kop td.logo { width: 68pt; text-align: left; }
        table.kop td.spacer { width: 68pt; }
        table.kop img { width: 62pt; height: 62pt; }
        .l1 { font-size: 12pt; font-weight: bold; margin: 0; }
        .l2 { font-size: 13pt; font-weight: bold; margin: 0; }
        .l3 { font-size: 11pt; font-weight: bold; margin: 0; }
        .l4 { font-size: 8pt; font-weight: bold; margin: 0; }
        .garis { border-bottom: 1.2pt solid #000; margin: 3pt 0 6pt 0; }

        /* Judul */
        .judul { text-align: center; font-weight: bold; margin: 0; }
        .judul.besar { font-size: 14pt; }
        .judul.sedang { font-size: 12pt; }

        /* Tabel rekap */
        table.rekap {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 20pt;
        }
        table.rekap th, table.rekap td {
            border: 0.75pt solid #000;
            padding: 2pt 4pt;
        }
        table.rekap thead { display: table-header-group; }
        table.rekap tr { page-break-inside: avoid; }
        table.rekap th.band {
            background: #ff0000;
            font-size: 16pt;
            font-weight: bold;
            text-align: center;
            padding: 3pt 0;
        }
        table.rekap th.kolom { font-weight: bold; text-align: center; }
        table.rekap td.c { text-align: center; }

        .footer {
            position: fixed;
            bottom: -30pt;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="footer">
        Hari efektif: {{ $hariEfektif }} hari
        &nbsp;|&nbsp;
        Periode data: {{ $from->copy()->locale('id')->translatedFormat('d F Y') }}
        s/d {{ $to->copy()->locale('id')->translatedFormat('d F Y') }}
    </div>

    @forelse ($kelas as $k)
        <div style="page-break-after: {{ $loop->last ? 'auto' : 'always' }};">
            <table class="kop">
                <tr>
                    <td class="logo">@if ($logo)<img src="{{ $logo }}">@endif</td>
                    <td>
                        <p class="l1">YAYASAN PENDIDIKAN ISLAM SUMBER BUNGUR</p>
                        <p class="l2">MADRASAH ALIYAH SUMBER BUNGUR PAKONG PAMEKASAN</p>
                        <p class="l3">Jl. Pontren Sumber Bungur Pakong Pamekasan Madura</p>
                        <p class="l4">e-mail:info@masumberbungur.sch.id &nbsp;&nbsp;&nbsp; Website: masumberbungur.sch.id</p>
                    </td>
                    <td class="spacer"></td>
                </tr>
            </table>
            <div class="garis"></div>

            <p class="judul besar">REKAP ABSENSI MURID</p>
            <p class="judul sedang">AKHIR SEMESTER {{ $semester }}</p>
            <p class="judul sedang">TAPEL {{ $tapel }}</p>

            <table class="rekap">
                <thead>
                    <tr>
                        <th class="band" colspan="6">{{ $k['nama'] }}</th>
                    </tr>
                    <tr>
                        <th class="kolom" style="width: 6%;">NO</th>
                        <th class="kolom" style="width: 17%;">IDK</th>
                        <th class="kolom" style="width: 41%;">NAMA</th>
                        <th class="kolom" style="width: 10%;">S</th>
                        <th class="kolom" style="width: 13%;">I</th>
                        <th class="kolom" style="width: 13%;">A</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($k['siswa'] as $row)
                        <tr>
                            <td class="c">{{ $row['no'] }}</td>
                            <td class="c">{{ $row['idk'] }}</td>
                            <td>{{ mb_strtoupper($row['nama']) }}</td>
                            <td class="c">{{ $row['s'] }}</td>
                            <td class="c">{{ $row['i'] }}</td>
                            <td class="c">{{ $row['a'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p style="text-align: center; margin-top: 80pt;">Tidak ada data siswa untuk filter ini.</p>
    @endforelse
</body>
</html>