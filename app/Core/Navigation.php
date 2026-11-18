<?php

namespace App\Core;

use App\Models\User;

/**
 * Membangun menu sidebar dari app/Config/navigation.php sesuai job dan izin
 * pengguna yang sedang login. Hanya untuk tampilan: setiap halaman tetap
 * dijaga permission middleware dan pengecekan kepemilikan di backend.
 */
class Navigation
{
    /**
     * @param array<string,mixed> $query query string request saat ini
     * @return array<int, array{section:string, items: array<int,array<string,mixed>>}>
     */
    public static function build(string $path, array $query): array
    {
        /** @var array<int, array<string,mixed>> $config */
        $config = require __DIR__ . '/../Config/navigation.php';
        $jobs = Jobs::of();
        $seen = [];
        $out = [];
        // Super Admin melihat SEMUA kelompok menu (setiap role lengkap dengan submenunya),
        // kecuali "Monitoring" yang hanya ringkasan ulang dari kelompok-kelompok itu.
        $all = in_array('super_admin', $jobs, true);

        foreach ($config as $section) {
            $want = $section['jobs'];
            if ($all) {
                if (($section['section'] ?? '') === 'Monitoring') {
                    continue;
                }
            } elseif (!in_array('*', $want, true) && array_intersect($want, $jobs) === []) {
                continue;
            }

            $items = [];
            foreach ($section['items'] as $item) {
                $prepared = self::prepare($item, $path, $query);
                if ($prepared === null) {
                    continue;
                }

                // Tautan yang sama tidak ditampilkan dua kali bila pengguna punya beberapa job
                // (daun maupun submenu). Induk tanpa submenu tersisa ikut disembunyikan.
                if (!$all) {
                    if (isset($prepared['children'])) {
                        $prepared['children'] = array_values(array_filter(
                            $prepared['children'],
                            static fn (array $c): bool => !isset($seen[$c['href']])
                        ));
                        if ($prepared['children'] === []) {
                            continue;
                        }
                        $prepared['active'] = in_array(true, array_column($prepared['children'], 'active'), true);
                    } elseif (isset($seen[$prepared['href']])) {
                        continue;
                    }
                }
                $hrefs = isset($prepared['children'])
                    ? array_column($prepared['children'], 'href')
                    : [$prepared['href']];
                foreach ($hrefs as $h) {
                    $seen[$h] = true;
                }

                $items[] = $prepared;
            }

            if ($items !== []) {
                $out[] = [
                    'section' => (string) $section['section'],
                    'id' => 'sbg-' . trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $section['section'])), '-'),
                    // Kelompok role (bukan Overview/Akun) dapat dibuka dan ditutup.
                    'collapsible' => !in_array('*', $want, true),
                    'active' => self::anyActive($items),
                    'items' => $items,
                ];
            }
        }

        return $out;
    }

    /** @param array<int,array<string,mixed>> $items */
    private static function anyActive(array $items): bool
    {
        foreach ($items as $item) {
            if (!empty($item['active'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $query
     * @return array<string,mixed>|null
     */
    private static function prepare(array $item, string $path, array $query): ?array
    {
        if (!empty($item['perm']) && !Acl::can((string) $item['perm'])) {
            return null;
        }
        if (!empty($item['when']) && !self::condition((string) $item['when'])) {
            return null;
        }

        if (isset($item['children'])) {
            $children = [];
            foreach ($item['children'] as $child) {
                $c = self::prepare($child, $path, $query);
                if ($c !== null) {
                    $children[] = $c;
                }
            }
            if ($children === []) {
                return null;
            }

            $item['children'] = $children;
            $item['active'] = in_array(true, array_column($children, 'active'), true);
            $item['id'] = 'sb-' . preg_replace('/[^a-z0-9]+/', '-', strtolower((string) $item['title']));

            return $item;
        }

        $item['active'] = empty($item['never_active']) && self::isActive($item, $path, $query);

        return $item;
    }

    /**
     * Syarat tampilnya sebuah menu di luar izin. Hanya untuk tampilan; akses data tetap
     * divalidasi backend.
     *
     * survey_support: Engineer yang ditugaskan sebagai personel pendukung pada Survey tertentu
     * (Super Admin selalu melihatnya).
     */
    private static function condition(string $name): bool
    {
        return match ($name) {
            'survey_support' => Jobs::has('super_admin') || User::hasSurveySupportAssignment((int) Auth::id()),
            default => false,
        };
    }

    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $query
     */
    private static function isActive(array $item, string $path, array $query): bool
    {
        $match = (string) ($item['match'] ?? '');
        if ($match === '') {
            return false;
        }

        if ($match === '/dashboard') {
            $root = rtrim((string) (parse_url(url('/'), PHP_URL_PATH) ?? ''), '/');

            return $path === '' || $path === $root || str_ends_with($path, '/dashboard');
        }

        if (!str_contains($path, $match)) {
            return false;
        }
        if (!empty($item['exclude']) && str_contains($path, (string) $item['exclude'])) {
            return false;
        }
        if (!empty($item['query'])) {
            foreach ($item['query'] as $k => $v) {
                if ((string) ($query[$k] ?? '') !== (string) $v) {
                    return false;
                }
            }
        }
        foreach ((array) ($item['no_query'] ?? []) as $k) {
            if ((string) ($query[$k] ?? '') !== '') {
                return false;
            }
        }

        return true;
    }
}
