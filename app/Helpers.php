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
   (getGroupAccessData). Node personal (Absensi Saya / Checklist Saya / Persetujuan)
   dibuang dari menu utama → pindah ke DROP-UP akun. Menu tetap DINAMIS per-role.
   ══════════════════════════════════════════════════════════════════════════ */

if (!function_exists('shellPersonalMap')) {
    /** link (segment-1) => [label tetap, ikon FontAwesome] untuk item drop-up akun. */
    function shellPersonalMap(): array
    {
        return [
            'akun'         => ['Absensi Saya',   'fas fa-calendar-check'],
            'my-checklist' => ['Checklist Saya', 'fas fa-tasks'],
            'persetujuan'  => ['Persetujuan',    'fas fa-check-circle'],
        ];
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

        $have = [];
        foreach ($data as $p) {
            if (!is_array($p)) continue;
            foreach (shellCollectLeaves($p) as $leaf) {
                if (isset($map[$leaf['link']])) $have[$leaf['link']] = true;
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

        foreach ($data as $p) {
            if (!is_array($p)) continue;
            $leaves = array_values(array_filter(
                shellCollectLeaves($p),
                fn ($l) => !in_array($l['link'], $personal, true)
            ));
            if (!$leaves) continue;

            foreach ($leaves as &$l) { $l['active'] = ($l['link'] === $controller); }
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
            foreach ($data as $row) {
                foreach (($row['sub'] ?? []) as $subrow) {
                    foreach (($subrow['child'] ?? []) as $childrow) {
                        if (($childrow['link'] ?? null) === $controller) {
                            return $childrow['nama'];
                        }
                    }
                }
            }
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
            $descNames = $role->descendants()->pluck('nama')->filter()->all();
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

            return $role->ancestors()->pluck('nama')->filter()->values()->all();
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

        $default = '📢 Isi & submit semua checklist harian sebelum pulang.'
            . '   •   Sterilisasi alat & jaga kebersihan ruang sesuai SOP.'
            . '   •   Sapa setiap pasien dengan senyum — Sweet, Cool & Friendly! 🦷';

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

