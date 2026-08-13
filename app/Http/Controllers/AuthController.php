<?php

namespace App\Http\Controllers;

use App\Mail\ForgotPassword;
use App\Models\Users;
use App\Models\SysProfile;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{
    public function login()
    {
        $sysprofile = SysProfile::select('systitle', 'syslogo')->first();

        return view('auth.login', compact('sysprofile'));
    }

    public function loginProcess()
    {
// $result = $this->_captcha();
// if (!$result->success) {
//     return redirect()->back()->with('error', 'Captcha verification failed')->withInput();
// }

        $this->validate(request(), [
            'email'    => 'required|email',
            'password' => 'required'
        ]);

        $data = [
            'email'    => request('email'),
            'password' => request('password')
        ];

        $user = Users::select('users.*', 'group_path.landing_page', 'group_path.nama as group_name', 'group_path.theme as group_theme')
            ->join('group_path', 'users.group_id', '=', 'group_path.id')
            ->where(['email' => $data['email'], 'status' => 1])
            ->first();

        $sysprofile = SysProfile::select('systitle', 'syslogo', 'sysname')->first();

        if ($user) {
            if (password_verify($data['password'], $user->password)) {
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
                    // Cabang EFEKTIF hari ini (rolling menang atas cabang asal),
                    // bukan mentah dari kolom — lihat cabangSesiUntuk().
                    'raw_branch_id'  => cabangSesiUntuk($user->id, $user->raw_branch_id),
                    'branch_day'     => date('Y-m-d'),
                    // Pusat Akun: stempel login (buat force-logout) + flag wajib-ganti
                    // + landing (dipakai redirect setelah ganti password)
                    'login_at'             => time(),
                    'must_change_password' => (int) ($user->must_change_password ?? 0) === 1,
                    'landing_page'         => $user->landing_page,
                ]);

                Users::where('id', $user->id)->update(['last_login' => date('Y-m-d H:i:s')]);

                \App\Models\AuditLog::record('login', 'User login: ' . $user->nama);

                // Kebersihan cookie perangkat (kasus HP/tablet dipakai bersama):
                // kalau perangkat ini masih "diingat" sebagai user LAIN, cabut —
                // tanpa ini, sesi user baru yang idle-expire bakal dibangun ulang
                // diam-diam sebagai user lama, dan absen tercatat ke orang salah.
                $deviceAuth = app(\App\Services\DeviceAuth::class);
                $devLama = \App\Models\UserDevice::findLive(request()->cookie(\App\Models\UserDevice::COOKIE_NAME));
                if ($devLama && $devLama->user_id !== $user->id) {
                    $deviceAuth->revokeCurrent(request());
                }

                // "Ingat perangkat ini" — SOP: hanya dicentang di HP pribadi.
                if (request('remember_device')) {
                    $deviceAuth->issue($user->id, request());
                }

                // Dari HP, mendarat di Absensi Saya (lihat landingUntukPerangkat).
                return redirect()->to(landingUntukPerangkat($user->landing_page, $user->group_id));
            } else {
                return redirect()->back()->with('error', 'Email or password is incorrect')->withInput();
            }
        } else {
            return redirect()->back()->with('error', 'Email tidak terdaftar, atau akun belum aktif')->withInput();
        }
    }

    public function forgotPassword()
    {
        $sysprofile = SysProfile::select('systitle', 'syslogo')->first();

        return view('auth.forgotPassword', compact('sysprofile'));
    }

    public function forgotPasswordProcess()
    {
        $result = $this->_captcha();

        if (!$result->success) {
            return redirect()->back()->with('error', 'Captcha verification failed');
        }

        $this->validate(request(), [
            'email' => 'required|email'
        ]);

        $user = Users::where('email', request('email'))->first();

        if ($user) {
            $token = bin2hex(random_bytes(16));

            Users::where('email', request('email'))->update(['reset_token' => $token]);

            Mail::to(request('email'))->send(new ForgotPassword($user, $token));

            return redirect()->route('signin')->with('success', 'New password has been sent to your email');
        } else {
            return redirect()->back()->with('error', 'Email not found');
        }
    }

    public function resetPassword($token)
    {
        $user = Users::where('reset_token', $token)->first();

        if ($user) {
            $sysprofile = SysProfile::select('systitle', 'syslogo')->first();
            return view('auth.resetPassword', compact('sysprofile', 'token'));
        } else {
            return redirect()->route('signin')->with('error', 'Invalid token');
        }
    }

    public function resetPasswordProcess($token)
    {
        $this->validate(request(), [
            'password'              => 'required|min:6',
            'password_confirmation' => 'required|same:password'
        ]);

        $user = Users::where('reset_token', $token)->first();

        if ($user) {
            Users::where('reset_token', $token)->update([
                'password'    => password_hash(request('password'), PASSWORD_DEFAULT),
                'reset_token' => null
            ]);

            // Ganti password = semua perangkat "diingat" dicabut (standar keamanan).
            app(\App\Services\DeviceAuth::class)->revokeAllFor($user->id);

            return redirect()->route('signin')->with('success', 'Password has been reset');
        } else {
            return redirect()->route('signin')->with('error', 'Invalid token');
        }
    }

    /**
     * Penjaga route ganti-password (di luar AuthGuard, jadi wajib jaga sendiri).
     * Ini pintu SENSITIF: kalau cuma cek isLoggedIn, sesi penyusup yang sudah
     * di-Reset/Paksa-Logout oleh IT bisa balik merebut akun lewat sini.
     */
    private function gantiPasswordGate()
    {
        if (! session('isLoggedIn')) return redirect()->route('signin');

        // sesi idle kadaluarsa
        if ((int) session('expires_in') < time()) {
            session()->flush();
            return redirect()->route('signin')->with('error', 'Sesi login telah berakhir, silahkan login kembali');
        }

        // IT menekan Paksa Logout / Reset SETELAH sesi ini login → tendang.
        $u = Users::where('id', session('id'))->first(['force_logout_at']);
        if ($u && $u->force_logout_at && strtotime($u->force_logout_at) > (int) session('login_at')) {
            session()->flush();
            return redirect()->route('signin')->with('error', 'Sesi kamu diakhiri oleh IT. Silakan login kembali.');
        }

        return null;
    }

    /** Halaman ganti password (wajib saat must_change_password; sukarela juga boleh). */
    public function gantiPassword()
    {
        if ($tolak = $this->gantiPasswordGate()) return $tolak;
        $sysprofile = SysProfile::select('systitle', 'syslogo')->first();
        return view('auth.gantiPassword', compact('sysprofile'));
    }

    public function gantiPasswordProcess()
    {
        if ($tolak = $this->gantiPasswordGate()) return $tolak;

        $wajib = (bool) session('must_change_password');
        $this->validate(request(), [
            'password'              => 'required|min:6',
            'password_confirmation' => 'required|same:password',
        ] + ($wajib ? [] : ['password_lama' => 'required']));

        $user = Users::where('id', session('id'))->first();
        if (! $user) return redirect()->route('signin');

        // Ganti sukarela WAJIB buktikan password lama — tanpa ini, orang yang
        // nemu sesi kebuka (HP ketinggalan) bisa merebut akun permanen.
        if (! $wajib && ! password_verify((string) request('password_lama'), $user->password)) {
            return redirect()->back()->withErrors(['password_lama' => 'Password lama salah.']);
        }

        Users::where('id', $user->id)->update([
            'password'             => password_hash(request('password'), PASSWORD_DEFAULT),
            'must_change_password' => 0,
        ]);
        session(['must_change_password' => false]);

        // Standar keamanan yang sama dengan reset: semua perangkat "diingat" dicabut.
        app(\App\Services\DeviceAuth::class)->revokeAllFor($user->id);

        \App\Models\AuditLog::record('update', 'Ganti password sendiri' . ($wajib ? ' (wajib)' : '') . ': ' . session('name'));

        return redirect()->to(session('landing_page') ?: '/')->with('success', 'Password berhasil diganti.');
    }

    public function logout()
    {
        \App\Models\AuditLog::record('logout', 'User logout: ' . session('name'));
        // Logout manual = niat eksplisit → perangkat "diingat" ikut dicabut,
        // biar HP hilang/pinjam bisa dimatikan dengan sekali logout.
        app(\App\Services\DeviceAuth::class)->revokeCurrent(request());
        session()->flush();
        return redirect()->route('signin');
    }

    private function _captcha()
    {
        $recaptcha = request('g-recaptcha-response');
        $secret    = env('CAPTCHA_SECRET_KEY');

        $client   = new Client();
        $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
            'form_params' => [
                'secret'   => $secret,
                'response' => $recaptcha
            ]
        ]);

        $result = json_decode($response->getBody()->getContents());

        return $result;
    }
}