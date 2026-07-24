<?php

namespace App\Http\Middleware;

use App\Models\GroupAccess;
use App\Models\GroupPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsLoggedIn
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (session()->has('isLoggedIn')) {
            $expires_in = session('expires_in');
            $current_time = time();

            if ($expires_in < $current_time) {
                session()->flush();
                return redirect()->route('signin')->with('error', 'Session has expired, please login again');
            }

            // User is logged in and session is valid, redirect to home
            return redirect()->route('home');
        }

        // Buka /signin padahal perangkat "diingat" → langsung masuk tanpa form.
        // HANYA pada GET — POST (submit form login) tidak boleh di-attempt,
        // supaya kredensial yang dikirim selalu diproses AuthController (yang
        // juga mencabut cookie perangkat user lain). Konsekuensi sadar: selama
        // perangkat diingat, buka /signin = auto-masuk; ganti akun lewat
        // logout dulu (yang sekaligus mencabut perangkat).
        if ($request->isMethod('get') && app(\App\Services\DeviceAuth::class)->attempt($request)) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
