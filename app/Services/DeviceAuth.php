<?php

namespace App\Services;

use App\Models\SysProfile;
use App\Models\UserDevice;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * "Ingat perangkat ini" — auto-login via cookie perangkat (90 hari).
 *
 * Kenapa ada: sesi custom kita idle-timeout 1 jam (AuthGuard) + Laravel 120
 * menit, jadi staff yang buka cuma 2×/hari (absen masuk/pulang) PASTI login
 * ulang tiap buka. Cookie perangkat nutup itu tanpa melonggarkan sesi.
 *
 * Keamanan (docs/rencana-absen-hp.md §5):
 *   • DB nyimpen HASH sha256 token; token asli cuma di cookie (ter-encrypt
 *     Laravel EncryptCookies di atasnya).
 *   • Token DIPUTAR tiap dipakai — cookie curian kepakai sekali, aslinya mati.
 *   • revoked_at terisi = mati permanen. Dicabut saat: logout manual,
 *     reset password, akun nonaktif (status!=1 ditolak saat attempt).
 *   • SOP: centang hanya di HP pribadi.
 */
class DeviceAuth
{
    /** Terbitkan token buat perangkat ini (dipanggil saat login dgn checkbox). */
    public function issue(string $userId, Request $request): void
    {
        $token = bin2hex(random_bytes(32));

        $device = UserDevice::create([
            'user_id'      => $userId,
            'token_hash'   => hash('sha256', $token),
            'label'        => $this->labelDari($request->userAgent() ?? ''),
            'last_used_at' => now(),
        ]);

        \App\Models\AuditLog::record('create', 'Perangkat "diingat" baru: ' . $device->label, $device);

        $this->queueCookie($token);
    }

    /**
     * Coba auto-login dari cookie perangkat. Sukses = session kebangun ulang
     * (payload SAMA persis dengan AuthController::loginProcess) + token diputar.
     */
    public function attempt(Request $request): bool
    {
        $device = UserDevice::findLive($request->cookie(UserDevice::COOKIE_NAME));
        if (!$device) return false;

        $user = Users::select('users.*', 'group_path.landing_page', 'group_path.nama as group_name', 'group_path.theme as group_theme')
            ->join('group_path', 'users.group_id', '=', 'group_path.id')
            ->where(['users.id' => $device->user_id, 'status' => 1])
            ->first();

        // Akun hilang/nonaktif → token ikut mati, jangan dibiarkan menggantung.
        if (!$user) {
            $device->forceFill(['revoked_at' => now()])->save();
            return false;
        }

        $sysprofile = SysProfile::select('systitle', 'syslogo', 'sysname')->first();

        session([
            'id'             => $user->id,
            'group_id'       => $user->group_id,
            'group_name'     => $user->group_name,
            'group_theme'    => $user->group_theme ?: 'theme-default',
            'expires_in'     => time() + 3600,
            'name'           => $user->nama,
            'systitle'       => $sysprofile->systitle,
            'syslogo'        => $sysprofile->syslogo,
            'sysname'        => $sysprofile->sysname,
            'isLoggedIn'     => true,
            'raw_branch_id'  => $user->raw_branch_id,
            'login_at'             => time(),
            'must_change_password' => (int) ($user->must_change_password ?? 0) === 1,
            'landing_page'         => $user->landing_page,
        ]);

        // Rotasi token: yang lama hangus detik ini juga.
        $baru = bin2hex(random_bytes(32));
        $device->forceFill([
            'token_hash'   => hash('sha256', $baru),
            'last_used_at' => now(),
        ])->save();
        $this->queueCookie($baru);

        Users::where('id', $user->id)->update(['last_login' => date('Y-m-d H:i:s')]);
        \App\Models\AuditLog::record('login', 'User login (perangkat diingat): ' . $user->nama);

        return true;
    }

    /** Cabut perangkat yang sedang dipakai (logout manual). */
    public function revokeCurrent(Request $request): void
    {
        $device = UserDevice::findLive($request->cookie(UserDevice::COOKIE_NAME));
        if ($device) {
            $device->forceFill(['revoked_at' => now()])->save();
        }
        Cookie::queue(Cookie::forget(UserDevice::COOKIE_NAME));
    }

    /** Cabut SEMUA perangkat user (reset/ganti password). */
    public function revokeAllFor(string $userId): void
    {
        UserDevice::where('user_id', $userId)->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    private function queueCookie(string $token): void
    {
        Cookie::queue(cookie(
            UserDevice::COOKIE_NAME,
            $token,
            UserDevice::COOKIE_DAYS * 24 * 60,
            '/',
            null,
            null,   // secure ikut request (dev http, prod https)
            true,   // httpOnly — JS tak boleh baca
            false,
            'lax'
        ));
    }

    /** Label perangkat buat mata manusia: "Android · Chrome". */
    private function labelDari(string $ua): string
    {
        return self::labelFromUa($ua);
    }

    /** Sama, versi statis — dipakai absen HP buat ngisi kolom Perangkat. */
    public static function labelFromUa(string $ua): string
    {
        $os = 'Perangkat';
        if (stripos($ua, 'android') !== false)      $os = 'Android';
        elseif (stripos($ua, 'iphone') !== false)   $os = 'iPhone';
        elseif (stripos($ua, 'ipad') !== false)     $os = 'iPad';
        elseif (stripos($ua, 'windows') !== false)  $os = 'Windows';
        elseif (stripos($ua, 'mac') !== false)      $os = 'Mac';

        $browser = '';
        if (stripos($ua, 'edg') !== false)          $browser = 'Edge';
        elseif (stripos($ua, 'chrome') !== false)   $browser = 'Chrome';
        elseif (stripos($ua, 'safari') !== false)   $browser = 'Safari';
        elseif (stripos($ua, 'firefox') !== false)  $browser = 'Firefox';

        return $browser ? ($os . ' · ' . $browser) : $os;
    }

    /** Perangkat non-HP? (Windows/Mac = laptop/PC) → penanda buat SPV, bukan blokir. */
    public static function isDesktopUa(string $ua): bool
    {
        $mobile = stripos($ua, 'android') !== false || stripos($ua, 'iphone') !== false
               || stripos($ua, 'ipad') !== false || stripos($ua, 'mobile') !== false;
        $desktop = stripos($ua, 'windows') !== false || stripos($ua, 'macintosh') !== false
                || stripos($ua, 'mac os') !== false || stripos($ua, 'linux') !== false;
        return $desktop && ! $mobile;
    }
}
