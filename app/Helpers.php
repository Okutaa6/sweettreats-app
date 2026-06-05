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

