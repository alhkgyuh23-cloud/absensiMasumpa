<?php

return [
    // Absen masuk setelah jam ini dianggap telat
    'jam_masuk' => '06:50',

    // Jam absen pulang DIBUKA. Sekaligus jam absen masuk DITUTUP.
    'jam_pulang' => [
        'senin'  => '13:00',
        'selasa' => '12:30',
        'rabu'   => '12:30',
        'kamis'  => '12:30',
        'jumat'  => '10:00',
        'sabtu'  => '12:30',
    ],

    // Jam absen pulang DITUTUP. Lewat dari ini = "belum melakukan absen pulang".
    'batas_pulang' => [
        'senin'  => '13:30',
        'selasa' => '13:00',
        'rabu'   => '13:00',
        'kamis'  => '13:00',
        'jumat'  => '11:00',
        'sabtu'  => '13:00',
    ],
];