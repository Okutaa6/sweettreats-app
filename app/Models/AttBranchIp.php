<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * IP internet terdaftar per cabang — penanda lokasi absen HP.
 * Satu cabang boleh banyak IP (multi-jalur ISP); satu IP hanya satu cabang
 * (UNIQUE). Diisi via halaman admin IT + tombol "daftarkan IP" dari flag SPV.
 * Lihat docs/rencana/rencana-absen-hp.md §5.
 */
class AttBranchIp extends Model
{
    protected $table = 'att_branch_ip';

    protected $fillable = [
        'raw_branch_id', 'ip', 'label', 'status', 'last_seen_at', 'created_by', 'sumber',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function branch()
    {
        return $this->belongsTo(RawBranch::class, 'raw_branch_id', 'id');
    }

    /**
     * Cari cabang dari IP request. NULL = IP tak dikenal (bukan error — flag).
     *
     * IPv4 → cocok penuh. IPv6 → cocok prefix /48, dengan /64 yang persis
     * diutamakan.
     *
     * KENAPA /48, BUKAN /64 LAGI (diubah 2026-08-21, ACC Ekky)
     * ────────────────────────────────────────────────────────────────────────
     * Telkom memutar /64 pelanggan kira-kira tiap DUA HARI — Bintaro memakai
     * TUJUH /64 berbeda dalam 17 hari — sementara /48-nya tak pernah berubah.
     * Dengan pencocokan /64, tiap rotasi menghanguskan seluruh pendaftaran dan
     * seisi cabang jatuh ke "pilih cabang manual" sampai ada manusia yang
     * mendaftarkan nomor barunya. Terukur di prod sepanjang Agustus 2026:
     * 133 dari 474 absen HP (28,1%) gagal cocok, dan 57 dari 63 kegagalan IPv6
     * akan cocok seandainya yang dibandingkan /48.
     *
     * INI BUKAN PELONGGARAN KEAMANAN BARU. WifiBelajarSendiri sudah memakai
     * kekerabatan /48 sebagai pagar yang memutuskan pendaftaran otomatis (lihat
     * docblock AMBANG_ORANG di sana: "yang menjaga keamanan bukan angka ini,
     * tapi pagar kekerabatan /48"). Yang berubah cuma JEDANYA — dulu /64 baru
     * harus menunggu 2 saksi dalam 72 jam, sekarang langsung dikenali.
     *
     * Bukti /48 memang membedakan: lima cabang memakai lima /48 terpisah tanpa
     * tumpang-tindih, dan dua HP pribadi yang bertetangga — 2404:c0:5d10
     * (Talitha) vs 2404:c0:5d1a (Reza) — tetap jatuh di /48 berlainan.
     * Melebarkan ke /32 akan menyatukan keduanya; /48 tidak.
     *
     * ⚠️ ASUMSI YANG DIPEGANG: Telkom mendelegasikan /48 per pelanggan. Itu
     * kesimpulan dari 17 hari data prod, BUKAN janji tertulis dari Telkom.
     * Kalau suatu saat dua pelanggan berbagi /48, pagar ini bocor. Penjaganya:
     * AbsensiBranchIpController::store() menolak mendaftarkan /48 yang sudah
     * dimiliki cabang lain — jadi kebocoran ketahuan saat pendaftaran, bukan
     * diam-diam saat pencocokan.
     *
     * VETO IT MENANG ATAS KEKERABATAN: baris nonaktif yang ip-nya PERSIS teks
     * prefix bersih ("2001:448a:2092:d21::") berarti IT sudah melihat jalur itu
     * lalu bilang tidak — /48 sekerabat tak boleh menghidupkannya lagi. Baris
     * nonaktif BEREKOR (alamat satu HP, mis. "...d21:23d8:ac94:b34:8c05") BUKAN
     * veto: itu cuma bersih-bersih rotasi gaya lama. Pembedaan yang persis sama
     * dipakai WifiBelajarSendiri::nilai() — jangan dibuat berbeda di dua tempat.
     */
    public static function matchBranch(string $ip): ?self
    {
        $bin  = @inet_pton($ip);
        $isV6 = $bin !== false && strlen($bin) === 16;

        // ::ffff:x.x.x.x — IPv4 menyamar jadi IPv6. Tetap IPv4 (CGNAT seluler
        // bisa lewat bentuk ini), jangan sampai ikut jalur pelebaran prefix.
        $v4Menyamar = $isV6 && substr($bin, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff";

        // IPv4 (atau string tak valid) → jalur cepat cocok-penuh.
        //
        // Dibakukan dulu: ::ffff:36.77.252.85 dan 36.77.252.85 adalah alamat
        // yang SAMA, cuma ditulis berbeda, dan proxy bisa mengirim bentuk mana
        // saja. Tanpa pembakuan, baris yang lahir dari bentuk satu tak akan
        // pernah cocok dengan permintaan berbentuk lain — DAN tak akan pernah
        // bisa didaftarkan ulang, karena UNIQUE(ip) menolak bentuk kembarannya.
        // Macet senyap; persis jenis kegagalan yang tak kelihatan dari layar.
        if (! $isV6 || $v4Menyamar) {
            $cari = static::teksIpv4($ip) ?? $ip;
            return static::where('ip', $cari)->where('status', 'aktif')->first();
        }

        $p64        = substr($bin, 0, 8);
        $p48        = substr($bin, 0, 6);
        $prefixTeks = (string) inet_ntop($p64 . str_repeat("\0", 8));

        $persisAktif = null;   // baris aktif ber-/64 sama → paling tepat
        $adaVeto     = false;  // baris nonaktif ber-teks-prefix persis → IT bilang tidak
        $kerabat     = null;   // baris aktif se-/48 → cadangan saat rotasi

        // Banding BINER, bukan SQL: satu prefix punya tak terhingga bentuk
        // tertulis ('::' terkompresi, huruf besar/kecil, ekor privacy extension
        // yang berganti tiap sesi). Tabelnya puluhan baris, sekali muat cukup.
        // orderBy('id') supaya pilihan kerabat deterministik, bukan urutan acak.
        foreach (static::orderBy('id')->get() as $row) {
            $rbin = @inet_pton((string) $row->ip);
            if ($rbin === false || strlen($rbin) !== 16) {
                continue;
            }

            if (substr($rbin, 0, 8) === $p64) {
                if ($row->status === 'aktif') {
                    $persisAktif = $persisAktif ?: $row;
                } elseif ((string) $row->ip === $prefixTeks) {
                    $adaVeto = true;
                }
                continue;
            }

            if ($kerabat === null && $row->status === 'aktif' && substr($rbin, 0, 6) === $p48) {
                $kerabat = $row;
            }
        }

        if ($persisAktif) {
            return $persisAktif;
        }

        // IT sudah menolak prefix INI. Kekerabatan /48 tidak boleh membatalkan
        // keputusan manusia — biar jatuh ke pilih-cabang-manual seperti dulu.
        if ($adaVeto) {
            return null;
        }

        return $kerabat;
    }

    /**
     * Bentuk baku sebuah IPv4, atau NULL kalau alamat ini bukan IPv4.
     *
     * Menerima kedua bentuk — "36.77.252.85" dan "::ffff:36.77.252.85" — dan
     * selalu mengembalikan yang bertitik. Dipakai bersama oleh pencocokan
     * (matchBranch) dan pembelajaran (WifiBelajarSendiri): satu alamat harus
     * punya satu nama, kalau tidak dua tempat akan menjawab berbeda untuk
     * pertanyaan yang sama.
     */
    public static function teksIpv4(string $ip): ?string
    {
        $bin = @inet_pton($ip);
        if ($bin === false) {
            return null;
        }
        if (strlen($bin) === 4) {
            return (string) inet_ntop($bin);
        }
        if (strlen($bin) === 16 && substr($bin, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
            return (string) inet_ntop(substr($bin, 12));
        }
        return null;
    }

    /**
     * Cabang lain yang sudah memiliki /48 milik $ip, kalau ada.
     *
     * Dipakai saat MENDAFTARKAN, bukan saat mencocokkan: karena pencocokan kini
     * melebar ke /48, satu /48 yang dipegang dua cabang membuat absen di salah
     * satunya terbaca sebagai cabang yang lain — diam-diam, tanpa gejala.
     * Lebih baik ditolak di pintu masuk.
     *
     * NULL untuk IPv4 (tak punya padanan /48) dan untuk /48 yang belum dimiliki
     * siapa pun, atau yang dimiliki cabang itu sendiri.
     */
    public static function pemilik48Lain(string $ip, string $branchId): ?self
    {
        $bin = @inet_pton($ip);
        if ($bin === false || strlen($bin) !== 16) {
            return null;
        }
        if (substr($bin, 0, 12) === "\0\0\0\0\0\0\0\0\0\0\xff\xff") {
            return null;
        }

        $p48 = substr($bin, 0, 6);

        foreach (static::where('status', 'aktif')->orderBy('id')->get() as $row) {
            if ((string) $row->raw_branch_id === $branchId) {
                continue;
            }
            $rbin = @inet_pton((string) $row->ip);
            if ($rbin !== false && strlen($rbin) === 16 && substr($rbin, 0, 6) === $p48) {
                return $row;
            }
        }
        return null;
    }
}
