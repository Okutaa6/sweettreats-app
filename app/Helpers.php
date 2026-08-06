<?php

function getGroupAccessData()
{
    $groupId = session('group_id');
    $cacheKey = 'group_access_' . $groupId;

    return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($groupId) {
        $filepath = storage_path('app/group_access/' . $groupId . '.txt');
        if (!file_exists($filepath)) {
            return null;
        }
        return json_decode(file_get_contents($filepath), true);
    });
}

function hasMultipleParents()
{
    $data = getGroupAccessData();
    return is_array($data) && count($data) > 1;
}

function getSidebarParent()
{
    $controller = request()->segment(1);

    $data = getGroupAccessData();

    if ($data !== null) {

        $result = '';
        if ($data) {
            $anyActive = false;
            foreach ($data as $row) {
                if (isParentActive($row, $controller)) { $anyActive = true; break; }
            }

            $first = true;
            foreach ($data as $row) {
                $isActive = isParentActive($row, $controller);
                // If no parent matches current controller, mark first parent active by default
                if (!$anyActive && $first) { $isActive = true; }
                $active = $isActive ? 'active' : '';
                $first = false;

                $paneId = 'pane-' . htmlspecialchars($row['id']);

                $result .= '<a href="#' . $paneId . '" class="nav-link ' . $active . '" data-parent-target="' . $paneId . '" data-toggle="tooltip-custom" data-placement="top" title="" data-original-title="' . htmlspecialchars($row['nama']) . '">' . PHP_EOL;
                $result .= '<i class="' . htmlspecialchars($row['icon']) . '"></i>' . PHP_EOL;
                $result .= '</a>' . PHP_EOL;
            }
        }
        return $result;
    } else {
        abort(404, 'File not found!');
    }
}

function getSidebarChild()
{
    $controller = request()->segment(1);

    $data = getGroupAccessData();

    if ($data !== null) {
        $result = '';
        if ($data) {
            $anyActive = false;
            foreach ($data as $row) {
                if (isParentActive($row, $controller)) { $anyActive = true; break; }
            }

            $first = true;
            foreach ($data as $row) {
                $isActive = isParentActive($row, $controller);
                if (!$anyActive && $first) { $isActive = true; }
                $active = $isActive ? 'active' : '';
                $first = false;

                if (isset($row['sub']) && !empty($row['sub'])) {
                    $result .= '<div id="pane-' . htmlspecialchars($row['id']) . '" class="main-icon-menu-pane ' . $active . '">' . PHP_EOL;
                    foreach ($row['sub'] as $subrow) {
                        $result .= '<div class="title-box">' . PHP_EOL;
                        $result .= '<h6 class="menu-title">' . htmlspecialchars($subrow['nama']) . '</h6>' . PHP_EOL;
                        $result .= '</div>' . PHP_EOL;

                        $result .= '<ul class="nav">' . PHP_EOL;
                        if (isset($subrow['child']) && !empty($subrow['child'])) {
                            foreach ($subrow['child'] as $childrow) {
                                $childactive = $controller == $childrow['link'] ? 'active' : '';

                                $result .= '<li class="nav-item"><a class="nav-link ' . $childactive . '" href="' . url(htmlspecialchars($childrow['link'])) . '"><i class="' . htmlspecialchars($childrow['icon']) . '"></i>' . htmlspecialchars($childrow['nama']) . '</a></li>' . PHP_EOL;
                            }
                        }
                        $result .= '</ul>' . PHP_EOL;
                    }
                    $result .= '</div>' . PHP_EOL;
                }
            }
        }
        return $result;
    } else {
        abort(404, 'Access not found');
    }
}

/* ══════════════════════════════════════════════════════════════════════════
   SHELL REDESIGN (2026-07) — render sidebar SATU-KOLOM dari data RBAC yang sama
   (getGroupAccessData). Node personal (Absensi Saya / Checklist Saya / Pengajuan /
   Persetujuan) dibuang dari menu utama → pindah ke DROP-UP akun. Menu tetap
   DINAMIS per-role.
   ══════════════════════════════════════════════════════════════════════════ */

if (!function_exists('shellPersonalMap')) {
    /**
     * link (segment-1) => [label tetap, ikon FontAwesome] untuk item drop-up akun.
     *
     * Isinya = kerjaan PRIBADI (dibawa orangnya, bukan jabatannya). Menu utama
     * dipakai untuk kerjaan ROLE. 'pengajuan' masuk sini sejak 2026-08-05:
     * mengajukan pengeluaran adalah tindakan pribadi — buktinya bagi Supervisor
     * & Head Marketing, section "Keuangan" di menu utama berdiri hanya untuk
     * menampung satu baris itu. Antrian & Rekap TIDAK ikut pindah: keduanya
     * kerjaan role Finance/pengelola, tempatnya memang di menu utama.
     */
    function shellPersonalMap(): array
    {
        return [
            'akun'         => ['Absensi Saya',   'fas fa-calendar-check'],
            'my-checklist' => ['Checklist Saya', 'fas fa-tasks'],
            // Riwayat checklist = milik sendiri juga. Node-nya hanya ada di
            // sebagian environment; entri di sini tak berefek apa pun di
            // environment yang tidak punya node itu.
            'my-checklist/history' => ['Riwayat Checklist', 'fas fa-history'],
            'pengajuan'    => ['Pengajuan',      'fas fa-file-invoice-dollar'],
            'persetujuan'  => ['Persetujuan',    'fas fa-check-circle'],
        ];
    }
}

if (!function_exists('shellSemuaNode')) {
    /**
     * SEMUA node ber-link di subtree — TERMASUK yang punya anak.
     *
     * Beda dengan shellCollectLeaves yang sengaja melewatkan node ber-anak: di
     * menu utama, node ber-anak berlaku sebagai judul section, bukan tautan.
     * Untuk drop-up akun aturan itu tak berlaku — sebuah node bisa merangkap
     * HALAMAN dan INDUK sekaligus, dan halamannya tak boleh hilang cuma karena
     * kebetulan punya anak.
     *
     * Kasus nyata (audit 2026-08-05): "Checklist Saya" (link `my-checklist`)
     * berubah jadi induk begitu leluhur-wadah disuntikkan ke pohon akses —
     * akibatnya halaman checklist milik General Services & Assistant Dentist
     * lenyap dari menu SAMA SEKALI, bukan cuma pindah tempat.
     */
    function shellSemuaNode(array $node): array
    {
        $out  = [];
        $link = trim((string) ($node['link'] ?? ''));
        if ($link !== '' && $link !== '-') {
            $out[] = ['nama' => $node['nama'] ?? $link, 'link' => $link];
        }
        foreach (($node['sub'] ?? $node['child'] ?? []) as $k) {
            if (is_array($k)) $out = array_merge($out, shellSemuaNode($k));
        }
        return $out;
    }
}

if (!function_exists('landingUntukPerangkat')) {
    /**
     * Halaman pertama sesudah login, disesuaikan dengan perangkat.
     *
     * Sebagian besar landing_page per-role menunjuk halaman lebar yang belum
     * responsif (marketing-dashboard, report, rawreservation, setgroup, sales-
     * dashboard). Dibuka dari HP, orang mendarat di halaman yang sulit dipakai —
     * padahal alasan utama membuka aplikasi dari HP adalah ABSEN. Lima role
     * memang sudah landing di 'akun'; sisanya tidak, termasuk Front Office yang
     * justru paling sering absen lewat HP.
     *
     * Aturannya sengaja sempit supaya tidak bikin kaget:
     *   • hanya HP (pointer-nya jempol), BUKAN tablet/desktop — tablet layarnya
     *     masih memadai untuk halaman lebar;
     *   • hanya pada saat MASUK (login / auto-login perangkat). Tautan langsung
     *     ke halaman mana pun tidak pernah dialihkan;
     *   • hanya kalau role-nya memang punya akses 'akun' — kalau tidak, dia akan
     *     mendarat di 403, dan itu lebih buruk daripada dashboard yang lebar.
     *
     * Deteksi lewat User-Agent memang kasar; risikonya kecil dan sepihak —
     * salah tebak paling banter membuat orang mendarat di halaman absennya
     * sendiri, satu klik dari mana pun.
     */
    function landingUntukPerangkat(?string $landing, ?string $groupId): string
    {
        $landing = trim((string) $landing);
        $fallback = $landing !== '' ? $landing : '/';

        if ($groupId === null || $groupId === '') return $fallback;

        $ua = (string) request()->header('User-Agent', '');
        // 'Mobile' ada di UA browser HP Android & iPhone; tablet Android dan
        // iPad justru TIDAK memuatnya (atau memuat 'iPad') → tablet lolos.
        $hp = preg_match('/iPhone|Windows Phone|Mobile/i', $ua) && !preg_match('/iPad|Tablet/i', $ua);
        if (! $hp) return $fallback;

        // Sudah mendarat di zona absensi pribadi → biarkan.
        if ($landing === 'akun' || strpos($landing, 'akun/') === 0) return $fallback;

        try {
            $punyaAkun = \App\Models\GroupAccess::join('access_path', 'group_access.access_id', '=', 'access_path.id')
                ->where('group_access.group_id', $groupId)
                ->where('access_path.link', 'akun')
                ->exists();
        } catch (\Throwable $e) {
            return $fallback;
        }

        return $punyaAkun ? 'akun' : $fallback;
    }
}

if (!function_exists('shellCollectLeaves')) {
    /**
     * Kumpulkan node NAVIGABLE (leaf ber-link) di subtree, rekursif.
     * Node yang punya anak = grup (section) → link-nya sendiri TIDAK di-emit,
     * cuma anak-anaknya. Cocok utk tree parent→sub→child dengan link di level mana pun.
     */
    function shellCollectLeaves(array $node): array
    {
        $kids = $node['sub'] ?? $node['child'] ?? [];
        if ($kids) {
            $out = [];
            foreach ($kids as $k) {
                if (is_array($k)) $out = array_merge($out, shellCollectLeaves($k));
            }
            return $out;
        }
        $link = trim((string) ($node['link'] ?? ''));
        if ($link === '' || $link === '-') return [];
        return [[
            'nama' => $node['nama'] ?? $link,
            'link' => $link,
            'icon' => trim((string) ($node['icon'] ?? '')),
        ]];
    }
}

if (!function_exists('shellAccountLinks')) {
    /**
     * Item personal untuk DROP-UP akun — hanya yang user punya grant-nya
     * (dinamis, ngecek getGroupAccessData). Persetujuan dapat badge = jumlah menunggu.
     */
    function shellAccountLinks(): array
    {
        $map  = shellPersonalMap();
        $data = getGroupAccessData();
        if (!is_array($data)) return [];

        // Pakai shellSemuaNode (bukan shellCollectLeaves): halaman pribadi yang
        // kebetulan punya anak tetap harus muncul di drop-up.
        $have = [];
        foreach ($data as $p) {
            if (!is_array($p)) continue;
            foreach (shellSemuaNode($p) as $n) {
                if (isset($map[$n['link']])) $have[$n['link']] = true;
            }
        }

        $out = [];
        foreach ($map as $link => [$label, $icon]) {
            if (empty($have[$link])) continue;
            $badge = null;
            if ($link === 'persetujuan') {
                try {
                    $badge = (new \App\Services\AttendanceRequestWorkflow())
                        ->pendingFor((string) session('id'))->count() ?: null;
                } catch (\Throwable $e) {}
            }
            $out[] = compact('label', 'link', 'icon', 'badge');
        }
        return $out;
    }
}

if (!function_exists('shellTabbedLinks')) {
    /**
     * Link yang halamannya sudah terjangkau lewat bar tab / tombol di dalam
     * halaman "pintu"-nya → tidak perlu baris sidebar sendiri. Peta:
     * link tab => link pintu tunggalnya.
     *
     * Ini murni soal RENDER menu. Grant node-nya TIDAK dicabut: node ber-link
     * 'absensi' adalah kunci AuthGuard untuk SEMUA /absensi/* (cabut grant =
     * seluruh halaman absensi role itu mati — terbukti di prod 2026-08-05,
     * lalu dikembalikan). Sembunyikan di sini, biarkan grant hidup.
     */
    function shellTabbedLinks(): array
    {
        return [
            'absensi'           => 'absensi/kehadiran', // Upload Data → tab di Kehadiran (_finance_tabs)
            'absensi/mapping'   => 'absensi/kehadiran',
            'absensi/recap'     => 'absensi/kehadiran',
            'absensi/periode'   => 'absensi/kehadiran',
            'finance/rop/rekap' => 'finance/rop',       // Rekap → tombol "Rekap" di Antrian
        ];
    }
}

if (!function_exists('shellMenuSections')) {
    /**
     * Menu utama single-column: daftar section {label, icon, items:[{nama,link,icon,active}]}.
     * Node personal dibuang (pindah ke drop-up). Parent yang isinya cuma personal → section
     * hilang sendiri. Section dengan 1 item tetap ditampilkan (label = nama parent).
     */
    function shellMenuSections(): array
    {
        $data = getGroupAccessData();
        if (!is_array($data)) return [];

        $personal   = array_keys(shellPersonalMap());
        $controller = request()->segment(1);
        $sections   = [];

        // Semua link yang role ini pegang — untuk tahu apakah "pintu" dari
        // sebuah link ber-tab ikut ter-grant (lihat shellTabbedLinks).
        $allLinks = [];
        foreach ($data as $p) {
            if (!is_array($p)) continue;
            foreach (shellCollectLeaves($p) as $l) $allLinks[$l['link']] = true;
        }

        // Link ber-tab disembunyikan kalau:
        //  a) role memegang pintunya (navigasi lewat tab tersedia), ATAU
        //  b) khusus klaster absensi: role toh 403 di halamannya
        //     (controller upload/mapping/recap/periode semua menjaga
        //     canSeeAllBranches) — baris sidebar yang berujung 403 adalah
        //     kegagalan senyap, bukan pintu.
        // 'finance/rop/rekap' TIDAK ikut aturan (b): Executive hanya pegang
        // Rekap tanpa Antrian — sembunyikan buta = pintu satu-satunya hilang.
        $tabbed = shellTabbedLinks();
        $hidden = [];
        $doorPatterns = [];
        foreach ($tabbed as $tab => $door) {
            $hasDoor = !empty($allLinks[$door]);
            $isAbsensiCluster = $door === 'absensi/kehadiran';
            if ($hasDoor || ($isAbsensiCluster && !canSeeAllBranches())) {
                $hidden[$tab] = true;
            }
            if ($hasDoor) {
                // Pintu ikut menyala saat user sedang berada di salah satu
                // halaman tab-nya (Upload/Mapping/Rekap/Periode → Kehadiran).
                if (!isset($doorPatterns[$door])) {
                    $doorPatterns[$door] = [$door, $door.'/*'];
                }
                $doorPatterns[$door][] = $tab;
                if (strpos($tab, '/') !== false) $doorPatterns[$door][] = $tab.'/*';
            }
        }

        // Ada leaf ber-sub-path yang cocok persis dengan URL sekarang?
        // (mis. WiFi Cabang 'absensi/wifi' saat membuka /absensi/wifi)
        // Kalau ada, leaf satu-segmen se-prefix (mis. 'absensi' = Upload Data)
        // TIDAK ikut menyala — dulu dua menu nyala barengan.
        $deepActive = false;
        foreach ($data as $p) {
            if (!is_array($p)) continue;
            foreach (shellCollectLeaves($p) as $l) {
                if (strpos((string) $l['link'], '/') !== false && request()->is($l['link'])) {
                    $deepActive = true;
                    break 2;
                }
            }
        }

        foreach ($data as $p) {
            if (!is_array($p)) continue;
            $leaves = array_values(array_filter(
                shellCollectLeaves($p),
                fn ($l) => !in_array($l['link'], $personal, true) && empty($hidden[$l['link']])
            ));
            if (!$leaves) continue;

            // Link ber-sub-path (mis. 'checklist/positions') dicocokkan ke path
            // penuh; link satu-segmen cukup cocok segment-1 — kecuali sedang ada
            // leaf sub-path yang cocok persis (lihat $deepActive di atas).
            // Link "pintu" (doorPatterns) menyala untuk seluruh klaster tab-nya.
            foreach ($leaves as &$l) {
                $link = (string) $l['link'];
                if (isset($doorPatterns[$link])) {
                    $l['active'] = request()->is(...$doorPatterns[$link]);
                } elseif (strpos($link, '/') !== false) {
                    $l['active'] = request()->is($link);
                } else {
                    $l['active'] = ! $deepActive && $link === $controller;
                }
            }
            unset($l);

            $sections[] = [
                'label' => $p['nama'] ?? '',
                'icon'  => trim((string) ($p['icon'] ?? '')),
                'items' => $leaves,
            ];
        }
        return $sections;
    }
}

/**
 * Nama halaman aktif untuk judul header — diambil dari menu (group_access)
 * berdasarkan child yang link-nya cocok dengan segment URL pertama. Konsisten
 * dengan label di sidebar. Fallback: title-case dari segment.
 */
if (!function_exists('getActivePageName')) {
    function getActivePageName()
    {
        $controller = request()->segment(1);
        $data = getGroupAccessData();
        if (is_array($data)) {
            // Cocokkan link ber-sub-path (mis. 'absensi/wifi') dulu — supaya
            // judul header ikut node yang paling spesifik, baru segment-1.
            $fallback = null;
            foreach ($data as $row) {
                foreach (($row['sub'] ?? []) as $subrow) {
                    // Node level-sub tanpa anak (mis. Kehadiran, Cuti, Aturan
                    // Kerja) adalah leaf juga — ikut dicek, bukan cuma 'child'.
                    $nodes = array_merge([$subrow], $subrow['child'] ?? []);
                    foreach ($nodes as $node) {
                        $link = $node['link'] ?? null;
                        if ($link && strpos($link, '/') !== false && request()->is($link)) {
                            return $node['nama'];
                        }
                        if ($link === $controller && $fallback === null) {
                            $fallback = $node['nama'];
                        }
                    }
                }
            }
            if ($fallback !== null) return $fallback;
        }
        return $controller ? ucwords(str_replace(['-', '_'], ' ', $controller)) : 'Dashboard';
    }
}

/**
 * Normalisasi nomor telepon ke 08xxxxxxxxx (null bila tak valid).
 * Wrapper tipis untuk App\Support\Phone::normalize agar bisa dipakai
 * di Blade / kode legacy tanpa import.
 */
if (!function_exists('normalize_phone')) {
    function normalize_phone(?string $raw): ?string
    {
        return \App\Support\Phone::normalize($raw);
    }
}

/**
 * True kalau user yang sedang login adalah Super Admin (berdasarkan
 * session('group_name')). Dipakai di Blade & controller untuk gating
 * fitur admin (mis. kelola sumber dana / fund_sources).
 */
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin(): bool
    {
        return session('group_name') === 'Super Admin';
    }
}

/**
 * True kalau tombol Reset Petty Cash (hard-delete jejak transaksi & riwayat
 * review) boleh muncul. Alat bersih-bersih data demo — default hanya hidup di
 * dev; di prod mati. Dipakai di controller (gate abort 404) & Blade (sembunyikan
 * tombol). Diatur via config('features.petty_cash_reset').
 */
if (!function_exists('pettyCashResetEnabled')) {
    function pettyCashResetEnabled(): bool
    {
        return (bool) config('features.petty_cash_reset');
    }
}

/**
 * True kalau user yang sedang login adalah Admin General. Dipakai bareng
 * isSuperAdmin() untuk gate fitur admin-level (mis. "Kelola Halaman" untuk
 * atur mode akses View Only per role).
 */
if (!function_exists('isAdminGeneral')) {
    function isAdminGeneral(): bool
    {
        return session('group_name') === 'Admin General';
    }
}

/**
 * True kalau user boleh mengatur mode akses per role di halaman tertentu
 * (Super Admin atau Admin General). Tombol "Kelola Halaman" di Raw
 * Reservation hanya muncul kalau ini true.
 */
if (!function_exists('canManagePageAccess')) {
    function canManagePageAccess(): bool
    {
        return isSuperAdmin() || isAdminGeneral();
    }
}

/**
 * True kalau user yang sedang login boleh melihat data lintas-cabang
 * (mis. dropdown cabang di Petty Cash, dashboard Monitoring Petty Cash).
 * Role yang boleh: Super Admin, Admin General, Admin Backup, Finance.
 *
 * Sales / staff cabang lain otomatis ke-lock ke session('raw_branch_id').
 */
if (!function_exists('canSeeAllBranches')) {
    function canSeeAllBranches(): bool
    {
        return in_array(session('group_name'), [
            'Super Admin',
            'Admin General',
            'Admin Backup',
            'Finance',
        ], true);
    }
}

/**
 * True kalau user yang sedang login adalah role Finance. Dipakai untuk
 * gate aksi mutasi di Petty Cash cabang — Finance hanya view-only,
 * tidak boleh tambah/edit/hapus transaksi, top-up, transfer, dll.
 */
if (!function_exists('isFinance')) {
    function isFinance(): bool
    {
        return session('group_name') === 'Finance';
    }
}

/**
 * True kalau user yang sedang login boleh mengedit data Petty Cash
 * (input pengeluaran, top-up, mutasi, edit/hapus transaksi, tutup
 * periode). False khusus untuk role Finance yang view-only.
 */
if (!function_exists('canEditPettyCash')) {
    function canEditPettyCash(): bool
    {
        return ! isFinance();
    }
}

/**
 * True kalau role yang login DICENTANG minimal satu halaman PENGAWAS checklist
 * di UI Akses (Set > Role > Akses). Sumber kebenaran SAMA dgn AuthGuard: join
 * group_access ⋈ access_path pada kolom `link` — bebas dari kedalaman pohon
 * menu (entri checklist ada di level "sub", bukan "child", jadi hasMenuAccess()
 * TIDAK bisa dipakai untuk ini). 'my-checklist' TIDAK dihitung (itu self-service
 * karyawan, bukan pengawas). Cached per-request.
 */
if (!function_exists('checklistHasSupervisorPageAccess')) {
    function checklistHasSupervisorPageAccess(): bool
    {
        static $cached = false;
        static $value  = false;

        if ($cached) return $value;
        $cached = true;

        $gid = session('group_id');
        if (! $gid) return $value = false;

        try {
            $value = \App\Models\GroupAccess::query()
                ->join('access_path', 'group_access.access_id', '=', 'access_path.id')
                ->where('group_access.group_id', $gid)
                ->whereIn('access_path.link', [
                    'checklist', 'checklist/positions', 'checklist/setup',
                    'checklist/review', 'checklist/audit',
                ])
                ->exists();
        } catch (\Throwable $e) {
            // Tabel akses belum ada / query gagal → anggap tak punya akses.
            $value = false;
        }

        return $value;
    }
}

/**
 * True kalau user yang sedang login adalah "overseer" checklist global —
 * melihat SEMUA divisi lintas penugasan.
 *
 * Hanya jaring pengaman admin: Super Admin / Admin General / Admin Backup selalu
 * lihat semua. Punya halaman Checklist TIDAK lagi berarti "lihat semua" — cakupan
 * divisi supervisor biasa ditentukan hierarki role (lihat checklistVisibleDivisionIds).
 */
if (!function_exists('checklistSeesAllDivisions')) {
    function checklistSeesAllDivisions(): bool
    {
        return in_array(session('group_name'), [
            'Super Admin',
            'Admin General',
            'Admin Backup',
        ], true);
    }
}

/**
 * Divisi checklist yang boleh DI-REVIEW user yang sedang login (sisi supervisor).
 *
 * Model "role operasional = divisi": divisi dikenali dari NAMA role bawahan yang
 * sama persis dgn nama divisi (Front Office↔FO, General Services↔GS, Assistant
 * Dentist↔ASDENT). Jadi:
 *   - Overseer global (checklistSeesAllDivisions) → SEMUA divisi aktif.
 *   - Selainnya → divisi yang namanya cocok dgn salah satu BAWAHAN (descendant,
 *     strict) role user. Bawahan non-operasional (Sales/HR/dst) tak punya divisi
 *     bernama sama → terabaikan. Role daun tak punya bawahan → kosong → bukan supervisor.
 *
 * Cached per-request (pola sama dgn currentUserPosition).
 */
if (!function_exists('checklistVisibleDivisionIds')) {
    function checklistVisibleDivisionIds(): array
    {
        static $cached = false;
        static $value  = [];

        if ($cached) return $value;
        $cached = true;

        try {
            $active = \App\Models\ChecklistDivision::where('is_active', 1);

            if (checklistSeesAllDivisions()) {
                return $value = $active->pluck('id')->all();
            }

            $gid  = session('group_id');
            $role = $gid ? \App\Models\GroupPath::find($gid) : null;
            if (! $role) return $value = [];

            // Strict descendants (tanpa diri) → staf tak me-review divisinya sendiri.
            // Cocokkan NAMA role bawahan ke NAMA divisi (hanya 3 role operasional yg cocok).
            // supervisedDescendants: ikut atasan utama DAN atasan kedua (parent_id_2)
            // → satu divisi bisa disupervisi dua atasan (mis. GS oleh Supervisor & Coordinator Dentist).
            $descNames = $role->supervisedDescendants()->pluck('nama')->filter()->all();
            if (! $descNames) return $value = [];

            $value = $active->whereIn('name', $descNames)->pluck('id')->all();
        } catch (\Throwable $e) {
            // Kolom/tabel belum di-migrate → anggap kosong biar UI tetap rapi.
            $value = [];
        }

        return $value;
    }
}

/**
 * Divisi checklist milik sebuah ROLE (group_path.id) — dikenali dari NAMA role
 * yang sama persis dgn nama divisi. Dipakai untuk menurunkan divisi user OTOMATIS
 * dari role-nya (tanpa pilih manual). Null kalau nama role tak cocok divisi mana pun.
 */
if (!function_exists('checklistDivisionForGroup')) {
    function checklistDivisionForGroup(?string $groupId): ?\App\Models\ChecklistDivision
    {
        if (! $groupId) return null;

        try {
            $role = \App\Models\GroupPath::find($groupId);
            if (! $role || ! $role->nama) return null;

            return \App\Models\ChecklistDivision::where('is_active', 1)
                ->where('name', $role->nama)
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}

/**
 * Nama role ATASAN (rantai ancestor, atasan langsung → atas) dari role staf
 * pemilik sebuah divisi. Dipakai untuk menargetkan notifikasi checklist ke
 * HIERARKI (via AdminNotification::notifyRole/target_role), bukan broadcast
 * cabang — supaya konsisten dgn wewenang review (checklistVisibleDivisionIds).
 * Kosong kalau divisi belum ditautkan ke role atau role tak punya atasan.
 */
if (!function_exists('checklistSupervisorRoleNamesForDivision')) {
    function checklistSupervisorRoleNamesForDivision(?string $divisionId): array
    {
        if (! $divisionId) return [];

        try {
            $div = \App\Models\ChecklistDivision::find($divisionId);
            if (! $div || ! $div->name) return [];

            // Role operasional = role yang namanya sama persis dgn nama divisi.
            $role = \App\Models\GroupPath::where('nama', $div->name)->first();
            if (! $role) return [];

            // supervisorAncestors: ikut atasan utama DAN atasan kedua (parent_id_2)
            // → notifikasi sampai ke semua atasan role (mis. GS ke Supervisor & Coordinator Dentist).
            return $role->supervisorAncestors()->pluck('nama')->filter()->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}

/**
 * True kalau user yang sedang login boleh mengelola/review modul Checklist
 * Akreditasi. DINAMIS: overseer global, atau role yang ditugaskan mensupervisi
 * minimal satu divisi (langsung / lewat bawahannya). Diatur di halaman Ubah Role.
 * Karyawan biasa tetap bisa akses /my-checklist (pengisian, bukan setup/review).
 */
if (!function_exists('isChecklistSupervisor')) {
    function isChecklistSupervisor(): bool
    {
        // Admin overseer = bypass. Selainnya WAJIB punya halaman Checklist (Kelola
        // Halaman) DAN minimal satu bawahan operasional (divisi cocok nama).
        return checklistSeesAllDivisions()
            || (checklistHasSupervisorPageAccess() && count(checklistVisibleDivisionIds()) > 0);
    }
}

/**
 * Teks Info / pengumuman (running-text) yang tampil di semua halaman my-checklist.
 * Global (satu teks untuk semua), disimpan di sys_setting key 'checklist_info_text'
 * dan bisa diedit admin di halaman Setup Tugas. Fallback ke teks default kalau
 * belum pernah diisi / tabel belum ada. Cached per-request.
 */
if (!function_exists('checklistInfoText')) {
    function checklistInfoText(): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        // Tanpa emoji (docs/spec/peta-tema.md §6) — teks ini tampil di UI
        // my-checklist, bukan keluaran CLI.
        $default = 'Isi & submit semua checklist harian sebelum pulang.'
            . '   •   Sterilisasi alat & jaga kebersihan ruang sesuai SOP.'
            . '   •   Sapa setiap pasien dengan senyum — Sweet, Cool & Friendly!';

        return $cached = (string) \App\Models\Setting::get('checklist_info_text', $default);
    }
}

/**
 * Resolve UserPosition aktif untuk user yang sedang login. Return null kalau
 * user belum di-assign divisi/cabang oleh supervisor — di flow /my-checklist
 * dipakai sebagai trigger empty state "Hubungi supervisor".
 *
 * Cached per-request supaya tidak query berulang antar view/controller.
 */
if (!function_exists('currentUserPosition')) {
    function currentUserPosition(): ?\App\Models\UserPosition
    {
        static $cached = false;
        static $value = null;

        if ($cached) return $value;
        $cached = true;

        $uid = session('id');
        if (! $uid) return $value = null;

        try {
            $value = \App\Models\UserPosition::query()
                ->activeOn()
                ->where('user_id', $uid)
                ->first();
        } catch (\Throwable $e) {
            // Tabel belum di-migrate / column missing — biarin null biar UI
            // empty-state-nya tampil rapi.
            $value = null;
        }

        return $value;
    }
}

/**
 * Cabang efektif seorang user pada tanggal tertentu. Rolling (penugasan
 * sementara) MENANG atas cabang asal (home) selama window-nya, sesudah itu
 * kembali ke home — semuanya ditentukan scopeActiveOn() (satu sumber:
 * sys_user_position). Return null kalau user tak punya posisi aktif di tanggal
 * itu. Dipakai mis. saat menandai hari cuti pada minggu yang belum pernah dibuka.
 */
if (!function_exists('positionBranchOn')) {
    function positionBranchOn(string $userId, \Carbon\Carbon $date): ?string
    {
        try {
            return \App\Models\UserPosition::query()
                ->activeOn($date)
                ->where('user_id', $userId)
                ->value('raw_branch_id');
        } catch (\Throwable $e) {
            return null;
        }
    }
}

/**
 * Branch ID yang dipakai untuk filter di halaman checklist supervisor.
 * Pakai pola sama dgn PettyCashController::resolveBranchId() — admin
 * bisa dropdown, role lain locked ke session.
 */
if (!function_exists('resolveChecklistBranchId')) {
    function resolveChecklistBranchId(?string $requested = null): ?string
    {
        $session = session('raw_branch_id');
        if ($session) return $session;
        if (! canSeeAllBranches()) return null;
        return $requested ?: null;
    }
}

/**
 * Jumlah unit AC yang TERLAMBAT dicuci di cabang user GS yang sedang login.
 * Dipakai untuk badge merah tombol "Cuci AC" di navbar my-checklist.
 * Return 0 kalau user bukan divisi GS / belum di-assign posisi / tabel
 * belum di-migrate. Cached per-request.
 */
if (!function_exists('acOverdueCountForCurrentGs')) {
    function acOverdueCountForCurrentGs(): int
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $position = currentUserPosition();
        if (! $position || $position->division_id !== 'GS' || ! $position->raw_branch_id) {
            return $cached = 0;
        }

        try {
            return $cached = \App\Models\AcUnit::overdueCountForBranch($position->raw_branch_id);
        } catch (\Throwable $e) {
            return $cached = 0;
        }
    }
}

/**
 * Daftar preset tema warna brand yang bisa di-assign ke role.
 * Key = nilai yang disimpan di group_path.theme dan dipakai untuk
 * resolve data-theme attribute di body.
 *
 *   - theme-default  → data-theme="default" → pakai brown brand existing
 *   - theme-marketing → data-theme="marketing" → pakai palette teal/lime
 *     dari public/assets/css/theme-marketing.css
 */
if (!function_exists('availableThemes')) {
    function availableThemes(): array
    {
        return [
            'theme-default'   => 'Default (Coklat)',
            'theme-marketing' => 'Marketing (Hijau Teal)',
        ];
    }
}

/**
 * True kalau role user di-set view_only untuk halaman Raw Reservation
 * (lewat tabel group_page_access_modes). Super Admin & Admin General
 * SELALU full access — tidak terpengaruh setting mode (admin override).
 *
 * Dipakai di view & controller untuk:
 *   - Hide tombol Tambah, tab Waiting List, kontrol inline edit
 *   - Block endpoint mutasi (store/update/destroy/inline/klaim)
 */
if (!function_exists('isReservationViewOnly')) {
    function isReservationViewOnly(): bool
    {
        if (isSuperAdmin() || isAdminGeneral()) {
            return false;
        }
        $gid = session('group_id');
        if (! $gid) return false;
        return \App\Models\GroupPageAccessMode::modeFor(
            (string) $gid,
            'rawreservation'
        ) === \App\Models\GroupPageAccessMode::MODE_VIEW_ONLY;
    }
}

/**
 * Check if a parent menu group contains a child link matching the current controller
 */
function isParentActive($parentRow, $controller)
{
    if (!$controller) return false;

    if (isset($parentRow['sub']) && !empty($parentRow['sub'])) {
        foreach ($parentRow['sub'] as $subrow) {
            if (isset($subrow['child']) && !empty($subrow['child'])) {
                foreach ($subrow['child'] as $childrow) {
                    if ($childrow['link'] == $controller) {
                        return true;
                    }
                }
            }
        }
    }
    return false;
}

/**
 * True kalau group yang sedang login punya akses ke menu node dengan
 * `link` tertentu (mis. 'report/executive'). Checkbox-driven: granting
 * menu lewat UI Akses → group_access → access:rebuild bikin fungsi ini
 * return true, TANPA perlu hardcode nama group di controller.
 *
 * Dipakai untuk gate sub-halaman yang AuthGuard tidak bisa bedakan
 * (AuthGuard hanya cek segment-1 URL). Super Admin selalu lolos.
 *
 * @param  string  $link  nilai access_path.link, mis. 'report/executive'
 */
if (!function_exists('hasMenuAccess')) {
    function hasMenuAccess(string $link): bool
    {
        if (isSuperAdmin()) {
            return true;
        }

        $data = getGroupAccessData();
        if (!is_array($data)) {
            return false;
        }

        foreach ($data as $parent) {
            foreach ($parent['sub'] ?? [] as $sub) {
                foreach ($sub['child'] ?? [] as $child) {
                    if (($child['link'] ?? null) === $link) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}

/**
 * Peta terpusat: tipe notifikasi (branch broadcast) → access_path.link
 * halaman/fitur tempat aksi notif itu berada.
 *
 * Dipakai untuk gate notifikasi supaya user cuma lihat notif fitur yang
 * bisa ia buka. Tipe di luar peta ini TIDAK di-gate (mis. pengumuman
 * 'system' global tetap tampil ke semua). Tambah entry di sini kalau ada
 * tipe/fitur baru yang perlu dibatasi.
 */
if (!function_exists('notificationTypeAccessMap')) {
    function notificationTypeAccessMap(): array
    {
        return [
            'reservation'   => 'rawreservation',
            'status_change' => 'rawreservation',
            'commission'    => 'rawreservation',
            'topup_request' => 'finance',
        ];
    }
}

/**
 * Daftar `type` notifikasi yang harus DISEMBUNYIKAN dari user aktif karena
 * ia tidak punya akses ke halaman/fitur terkait (lihat
 * notificationTypeAccessMap()).
 *
 * Cek akses pakai pola otoritatif yang sama dgn AuthGuard: join
 * group_access ⋈ access_path on link. Super Admin selalu lolos (return []).
 * Hasil di-cache per-request.
 */
if (!function_exists('notificationBlockedTypesForUser')) {
    function notificationBlockedTypesForUser(): array
    {
        static $cached = false;
        static $value  = [];

        if ($cached) return $value;
        $cached = true;

        if (function_exists('isSuperAdmin') && isSuperAdmin()) {
            return $value = [];
        }

        $gid = session('group_id');
        if (! $gid) {
            // Tanpa role → tak bisa buktikan akses ke fitur apapun → gate semua
            // tipe yang terpetakan.
            return $value = array_values(array_unique(array_keys(notificationTypeAccessMap())));
        }

        $map = notificationTypeAccessMap();

        // Cek akses per-link sekali saja (satu link bisa dipakai banyak tipe).
        $linkHasAccess = [];
        foreach ($map as $type => $link) {
            if (! array_key_exists($link, $linkHasAccess)) {
                try {
                    $linkHasAccess[$link] = \App\Models\GroupAccess::query()
                        ->join('access_path', 'group_access.access_id', '=', 'access_path.id')
                        ->where('group_access.group_id', $gid)
                        ->where('access_path.link', $link)
                        ->exists();
                } catch (\Throwable $e) {
                    // Tabel akses belum ada / query gagal → anggap tak punya akses.
                    $linkHasAccess[$link] = false;
                }
            }
            if (! $linkHasAccess[$link]) {
                $value[] = $type;
            }
        }

        $value = array_values(array_unique($value));
        return $value;
    }
}

