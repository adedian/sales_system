<?php

namespace App\Services;

/**
 * Mengubah koordinat atau link Google Maps menjadi detail lokasi.
 *
 * Masukan yang dikenali:
 *  - koordinat polos: "-7.2575, 112.7521"
 *  - link Google Maps panjang (…/@-7.25,112.75,17z, …!3d-7.25!4d112.75, …?q=-7.25,112.75)
 *  - link pendek (maps.app.goo.gl/…, goo.gl/maps/…) — diikuti redirect-nya di server
 *
 * Nama jalan/kota diambil dari OpenStreetMap Nominatim (gratis, tanpa API key).
 * Hanya koordinat yang dikirim ke layanan itu — tidak ada data customer.
 */
class LocationResolver
{
    private const SHORT_HOSTS = ['maps.app.goo.gl', 'goo.gl', 'g.co'];
    private const GOOGLE_HOST = '/(^|\.)google\.[a-z.]+$/i';
    private const USER_AGENT = 'HME-Sales-System/1.0';

    /**
     * @return array{lat:float,lng:float,city:string,province:string,address:string,maps_url:string}
     * @throws \RuntimeException pesan aman ditampilkan ke pengguna
     */
    public static function resolve(string $input): array
    {
        $input = trim($input);
        if ($input === '' || mb_strlen($input) > 600) {
            throw new \RuntimeException('Masukkan koordinat atau link Google Maps.');
        }

        $isUrl = (bool) preg_match('#^https?://#i', $input);
        if ($isUrl) {
            $host = strtolower((string) parse_url($input, PHP_URL_HOST));
            if (!in_array($host, self::SHORT_HOSTS, true) && !preg_match(self::GOOGLE_HOST, $host)) {
                throw new \RuntimeException('Hanya link Google Maps yang didukung.');
            }
        }

        $finalUrl = $input;
        [$lat, $lng] = self::extractCoordinates($input) ?? [null, null];

        // link pendek (maps.app.goo.gl) tidak memuat koordinat: ikuti redirect-nya
        if ($isUrl && ($lat === null || in_array($host ?? '', self::SHORT_HOSTS, true))) {
            $finalUrl = self::followRedirects($input);
            [$lat, $lng] = self::extractCoordinates($finalUrl) ?? [$lat, $lng];
        }

        if ($lat === null || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new \RuntimeException('Koordinat tidak ditemukan. Tempel koordinat (mis. -7.2575, 112.7521) atau link Google Maps yang memuat titik lokasi.');
        }

        $detail = self::reverseGeocode($lat, $lng);

        return [
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'city' => $detail['city'],
            'province' => $detail['province'],
            'address' => $detail['address'],
            'maps_url' => sprintf('https://www.google.com/maps?q=%.7f,%.7f', $lat, $lng),
            'place_name' => $isUrl ? self::placeName($finalUrl) : '',
        ];
    }

    /** Nama tempat pada link Google Maps (/maps/place/<nama>/@...), mis. "PT Hexa Multi Energi"; kosong bila hanya koordinat. */
    private static function placeName(string $url): string
    {
        if (!preg_match('#/maps/place/([^/@?]+)#', $url, $m)) {
            return '';
        }
        $name = trim(urldecode(str_replace('+', ' ', $m[1])));

        return preg_match('/^-?\d+(\.\d+)?\s*,/', $name) ? '' : mb_substr($name, 0, 150);
    }

    /** @return array{0:float,1:float}|null */
    private static function extractCoordinates(string $text): ?array
    {
        $text = urldecode($text);
        $num = '(-?\d{1,3}(?:\.\d+)?)';
        $patterns = [
            '/^\s*' . $num . '\s*[,;\s]\s*' . $num . '\s*$/',      // "-7.25, 112.75"
            '/!3d' . $num . '!4d' . $num . '/',                    // titik pin sebenarnya
            '/@' . $num . ',' . $num . '/',                        // pusat peta
            '/[?&](?:q|query|ll|center|destination)=' . $num . '(?:,|\s)\s*' . $num . '/',
            '#/place/' . $num . ',' . $num . '#',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $text, $m)) {
                return [(float) $m[1], (float) $m[2]];
            }
        }

        return null;
    }

    private static function followRedirects(string $url): string
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('Ekstensi cURL PHP belum aktif.');
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 6,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; HME-Sales-System/1.0)',
            CURLOPT_NOBODY => false,
            CURLOPT_RANGE => '0-0', // cukup tahu alamat akhir, tidak perlu isi halaman
        ]);
        curl_exec($ch);
        $final = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $err = curl_error($ch);
        curl_close($ch);

        if ($final === '' || $final === $url && $err !== '') {
            error_log('LocationResolver redirect: ' . $err);
            throw new \RuntimeException('Tidak dapat membuka link Google Maps. Tempel koordinatnya langsung.');
        }

        return $final;
    }

    private const PROVINCES = [
        'Aceh', 'Sumatera Utara', 'Sumatera Barat', 'Riau', 'Kepulauan Riau', 'Jambi', 'Sumatera Selatan', 'Bangka Belitung',
        'Kepulauan Bangka Belitung', 'Bengkulu', 'Lampung', 'DKI Jakarta', 'Jakarta', 'Banten', 'Jawa Barat', 'Jawa Tengah',
        'DI Yogyakarta', 'Yogyakarta', 'Jawa Timur', 'Bali', 'Nusa Tenggara Barat', 'Nusa Tenggara Timur', 'Kalimantan Barat',
        'Kalimantan Tengah', 'Kalimantan Selatan', 'Kalimantan Timur', 'Kalimantan Utara', 'Sulawesi Utara', 'Gorontalo',
        'Sulawesi Tengah', 'Sulawesi Barat', 'Sulawesi Selatan', 'Sulawesi Tenggara', 'Maluku', 'Maluku Utara', 'Papua',
        'Papua Barat', 'Papua Barat Daya', 'Papua Selatan', 'Papua Tengah', 'Papua Pegunungan', 'Indonesia',
    ];
    /** Hasil yang hanya mengenali satu kota/kabupaten/provinsi terlalu kasar — ditolak, bukan ditampilkan sebagai titik. */
    private const TOO_COARSE = ['city', 'town', 'county', 'state', 'state_district', 'region', 'province', 'country', 'municipality', 'city_district', 'district'];

    /**
     * Arah sebaliknya: alamat (teks bebas) -> koordinat + link Google Maps.
     *
     * Alamat Indonesia (ruko, blok, nomor unit) hampir tidak pernah ada utuh di
     * peta, jadi alamat dipecah menjadi gedung/kompleks, jalan, dan area, lalu
     * dicocokkan dari yang paling spesifik. Hasil tingkat kota ditolak — titik
     * di pusat kota lebih menyesatkan daripada tidak ada titik. `precision`:
     *   exact  alamat utuh cocok pada satu bangunan/tempat
     *   poi    kompleks/gedung yang sama ditemukan (bukan nomor unitnya)
     *   street jalannya ditemukan (nomor tidak dikenal)
     *   area   hanya kelurahan/kawasan yang dikenali
     *
     * @return array{lat:float,lng:float,city:string,province:string,address:string,maps_url:string,approx:bool,precision:string,matched:string}
     * @throws \RuntimeException
     */
    public static function searchAddress(string $address, string $cityHint = ''): array
    {
        $address = trim(preg_replace('/\s+/u', ' ', $address));
        $cityHint = trim($cityHint);
        if (mb_strlen($address) < 5 || mb_strlen($address) > 400) {
            throw new \RuntimeException('Alamat terlalu pendek untuk dicari.');
        }

        [$candidates, $city] = self::buildCandidates($address, $cityHint);
        $houseNo = preg_match('/\b(?:no\.?|nomor)\s*(\d+)/iu', $address, $m) ? $m[1] : '';

        foreach ($candidates as $i => $cand) {
            if ($i > 0) {
                usleep(1100000); // batas wajar Nominatim: 1 permintaan/detik
            }
            $rows = self::nominatim('search', ['q' => $cand['q'], 'countrycodes' => 'id', 'limit' => 5, 'addressdetails' => 1]);
            foreach (is_array($rows) ? $rows : [] as $row) {
                $precision = self::precisionOf($row, $cand['kind'], $city, $houseNo);
                if ($precision === null) {
                    continue;
                }
                $lat = (float) $row['lat'];
                $lng = (float) $row['lon'];
                $detail = self::parseAddress($row);

                return [
                    'lat' => round($lat, 7),
                    'lng' => round($lng, 7),
                    'city' => $detail['city'],
                    'province' => $detail['province'],
                    'address' => $detail['address'],
                    'maps_url' => sprintf('https://www.google.com/maps?q=%.7f,%.7f', $lat, $lng),
                    'approx' => $precision !== 'exact',
                    'precision' => $precision,
                    'matched' => mb_strimwidth($detail['display'], 0, 140, '…'),
                ];
            }
        }

        throw new \RuntimeException('Alamat belum ditemukan secara akurat di peta. Tempel link Google Maps atau koordinat agar titiknya tepat.');
    }

    /**
     * Pecah alamat -> kandidat pencarian berurutan dari spesifik ke umum.
     *
     * @return array{0:array<int,array{q:string,kind:string}>,1:string} [kandidat, kota]
     */
    private static function buildCandidates(string $address, string $cityHint): array
    {
        $parts = array_map('trim', explode(',', preg_replace('/\b\d{5}\b/u', '', $address)));
        $provinces = array_map('mb_strtolower', self::PROVINCES);
        $parts = array_values(array_filter($parts, static function (string $p) use ($provinces) {
            return $p !== '' && !in_array(mb_strtolower($p), $provinces, true) && !preg_match('/^(RT|RW)\b[\s\d\/.\-RTW]*$/iu', $p);
        }));
        $parts = array_map(static fn (string $p) => trim(preg_replace('/^(Kec\.?|Kecamatan|Kel\.?|Kelurahan|Desa|Kab\.?|Kabupaten|Kota)\s+/iu', '', $p)), $parts);

        $city = $cityHint !== '' ? $cityHint : (count($parts) >= 2 ? (string) end($parts) : '');
        $city = trim(preg_replace('/^(Kab\.?|Kabupaten|Kota)\s+/iu', '', $city));

        $isStreet = static fn (string $p) => (bool) preg_match('/^(jl\.?|jalan|jln\.?)\s/iu', $p);
        $noise = '/\b(blok|block|kav|kavling|unit|lt|lantai|no|nomor)\.?\s*[A-Za-z0-9\/\-]+|\b[A-Z]{1,3}-\d+[A-Z]?\b/iu';

        $street = '';
        $poi = '';
        $areas = [];
        foreach ($parts as $idx => $p) {
            if ($city !== '' && mb_strtolower($p) === mb_strtolower($city)) {
                continue;
            }
            if ($street === '' && $isStreet($p)) {
                $street = trim(preg_replace('/\s+/u', ' ', preg_replace($noise, '', $p)));
            } elseif ($idx === 0 && !$isStreet($p) && count($parts) > 1) {
                $name = trim(preg_replace('/^(ruko|rukan|gedung|komplek|kompleks|kawasan|perumahan|cluster)\s+/iu', '', $p));
                $poi = trim(preg_replace('/\s+/u', ' ', preg_replace($noise, '', $name)));
            } else {
                $areas[] = $p;
            }
        }
        $areas = array_values(array_unique($areas));
        $area = $areas[0] ?? '';
        $withCity = static fn (string $q) => ($city !== '' && stripos($q, $city) === false) ? $q . ', ' . $city : $q;

        $cands = [['q' => $withCity(implode(', ', $parts)), 'kind' => 'full']];
        if (mb_strlen($poi) >= 4) {
            $cands[] = ['q' => $withCity($poi), 'kind' => 'poi'];
        }
        if ($street !== '') {
            $cands[] = ['q' => $withCity($street . ($area !== '' ? ', ' . $area : '')), 'kind' => 'street'];
            if ($area !== '') {
                $cands[] = ['q' => $withCity($street), 'kind' => 'street'];
            }
        }
        if ($area !== '') {
            $cands[] = ['q' => $withCity($area), 'kind' => 'area'];
        }

        $seen = [];
        $out = [];
        foreach ($cands as $c) {
            $key = mb_strtolower($c['q']);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $c;
            }
        }

        return [array_slice($out, 0, 5), $city];
    }

    /** Tingkat ketepatan satu hasil Nominatim, atau null bila ditolak (terlalu kasar / kota berbeda). */
    private static function precisionOf(array $row, string $kind, string $city, string $houseNo = ''): ?string
    {
        if (empty($row['lat']) || empty($row['lon'])) {
            return null;
        }
        if (in_array($row['addresstype'] ?? '', self::TOO_COARSE, true)) {
            return null;
        }
        if ($city !== '' && stripos((string) ($row['display_name'] ?? ''), $city) === false) {
            return null; // nama sama di kota lain
        }
        $cat = $row['category'] ?? '';
        $type = $row['type'] ?? '';
        if ($cat === 'highway') {
            return 'street';
        }
        if ($cat === 'boundary' || ($cat === 'place' && !in_array($type, ['house', 'building', 'apartments', 'detached', 'terrace'], true))) {
            return 'area';
        }

        if ($kind !== 'full') {
            return 'poi';
        }

        // "exact" hanya bila nomor di alamat memang ada pada hasil (bukan bangunan tetangga, mis. No. 8A untuk No. 10)
        if ($houseNo !== '' && !preg_match('/(?<![\dA-Za-z])' . preg_quote($houseNo, '/') . '(?![\d])/u', (string) ($row['display_name'] ?? ''))) {
            return 'street';
        }

        return 'exact';
    }

    /** @return array{city:string,province:string,address:string} */
    private static function reverseGeocode(float $lat, float $lng): array
    {
        $data = self::nominatim('reverse', ['lat' => $lat, 'lon' => $lng, 'zoom' => 18, 'addressdetails' => 1]);
        if (!is_array($data) || empty($data['address'])) {
            // koordinat tetap valid; detail nama tempat diisi manual
            return ['city' => '', 'province' => '', 'address' => '', 'display' => ''];
        }

        return self::parseAddress($data);
    }

    /** Satu permintaan ke Nominatim; null bila gagal (jaringan/HTTP). Hanya teks alamat atau koordinat yang dikirim. */
    private static function nominatim(string $endpoint, array $params): mixed
    {
        $url = 'https://nominatim.openstreetmap.org/' . $endpoint . '?' . http_build_query($params + ['format' => 'jsonv2', 'accept-language' => 'id']);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status === 200 ? json_decode((string) $raw, true) : null;
    }

    /** @return array{city:string,province:string,address:string,display:string} */
    private static function parseAddress(array $row): array
    {
        $a = $row['address'] ?? [];
        $city = $a['city'] ?? $a['county'] ?? $a['regency'] ?? $a['town'] ?? $a['municipality'] ?? $a['city_district'] ?? '';
        $city = trim(preg_replace('/^(Kabupaten|Kab\.|Kota Administrasi|Kota)\s+/iu', '', (string) $city));

        // display_name diawali nama toko/POI terdekat ("Calipto, Jalan …") — itu bukan alamat.
        // Susun dari komponen: jalan, kelurahan/kawasan, kecamatan, kota, provinsi, kode pos.
        $neighbourhood = (string) ($a['neighbourhood'] ?? '');
        $chunks = [
            $a['road'] ?? '',
            preg_match('/^(RT|RW)\b/iu', $neighbourhood) ? '' : $neighbourhood,
            $a['suburb'] ?? ($a['village'] ?? ''),
            $a['city_district'] ?? '',
            (string) ($a['city'] ?? $a['county'] ?? $a['town'] ?? ''),
            $a['state'] ?? '',
        ];
        $chunks = array_values(array_unique(array_filter(array_map('trim', $chunks))));
        $formatted = implode(', ', $chunks) . (!empty($a['postcode']) ? ' ' . $a['postcode'] : '');

        return [
            'city' => $city,
            'province' => (string) ($a['state'] ?? ''),
            'address' => $chunks ? $formatted : (string) ($row['display_name'] ?? ''),
            'display' => (string) ($row['display_name'] ?? ''),
        ];
    }
}
