<?php

function i18n_locale(): string
{
    $locale = $_SESSION['user']['locale'] ?? $_SESSION['company']['locale'] ?? 'en';
    return in_array($locale, ['en', 'id'], true) ? $locale : 'en';
}

function t(string $key, array $replace = []): string
{
    static $catalog = [
        'en' => [
            'nav.dashboard' => 'Dashboard',
            'nav.reports' => 'All Reports',
            'nav.attendance' => 'Attendance',
            'nav.master' => 'Master Data',
            'nav.not_absent' => 'Not Absent',
            'nav.companies' => 'Companies',
            'nav.invite' => 'Invites',
            'nav.input' => 'Report Input',
            'nav.my_report' => 'My Report',
            'nav.profile' => 'My Profile',
            'empty.invite' => 'Invite your first employee to start tracking production.',
            'settings.saved' => 'Settings saved.',
            'settings.required' => 'All target fields are required.',
            'settings.positive' => 'Target values must be greater than 0.',
            'settings.min_max' => 'Minimum cannot be greater than maximum.',
            'tos.title' => 'Terms of Service',
            'privacy.title' => 'Privacy Policy',
        ],
        'id' => [
            'nav.dashboard' => 'Dasbor',
            'nav.reports' => 'Semua Laporan',
            'nav.attendance' => 'Absensi',
            'nav.master' => 'Data Master',
            'nav.not_absent' => 'Belum Absen',
            'nav.companies' => 'Perusahaan',
            'nav.invite' => 'Undangan',
            'nav.input' => 'Input Laporan',
            'nav.my_report' => 'Laporan Saya',
            'nav.profile' => 'Profil Saya',
            'empty.invite' => 'Undang karyawan pertama untuk mulai mencatat produksi.',
            'settings.saved' => 'Pengaturan tersimpan.',
            'settings.required' => 'Semua field target wajib diisi.',
            'settings.positive' => 'Nilai target harus lebih dari 0.',
            'settings.min_max' => 'Minimum tidak boleh lebih besar dari maksimum.',
            'tos.title' => 'Syarat Layanan',
            'privacy.title' => 'Kebijakan Privasi',
        ],
    ];

    $locale = i18n_locale();
    $text = $catalog[$locale][$key] ?? $catalog['en'][$key] ?? $key;
    foreach ($replace as $k => $v) {
        $text = str_replace('{' . $k . '}', (string) $v, $text);
    }
    return $text;
}
