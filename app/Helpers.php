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

