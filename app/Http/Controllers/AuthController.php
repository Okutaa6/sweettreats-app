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
                    'raw_branch_id'  => $user->raw_branch_id,
                ]);

                Users::where('id', $user->id)->update(['last_login' => date('Y-m-d H:i:s')]);

                \App\Models\AuditLog::record('login', 'User login: ' . $user->nama);

                return redirect()->to($user->landing_page);
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

            return redirect()->route('signin')->with('success', 'Password has been reset');
        } else {
            return redirect()->route('signin')->with('error', 'Invalid token');
        }
    }

    public function logout()
    {
        \App\Models\AuditLog::record('logout', 'User logout: ' . session('name'));
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