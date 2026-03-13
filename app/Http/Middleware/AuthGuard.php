<?php

namespace App\Http\Middleware;

use App\Models\GroupAccess;
use App\Models\GroupPath;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('isLoggedIn')) { // check if user is logged in
            return redirect()->route('signin')->with('error', 'Silahkan login terlebih dahulu');
        }

        $expires_in = session('expires_in');
        $current_time = time();

        if ($expires_in < $current_time) { // check if session is expired
            session()->flush();
            return redirect()->route('signin')->with('error', 'Sesi login telah berakhir, silahkan login kembali');
        }

        // Get the first URI segment as controller identifier
        $controller = $request->segment(1);
        $group_id = session()->get('group_id');

        // Allow home route (/) without access check
        if ($controller === null || $controller === '') {
            // Refresh session expiry
            session(['expires_in' => time() + 3600]);
            return $next($request);
        }

        // Check if user has access to the controller
        $groupAccess = GroupAccess::join('access_path', 'group_access.access_id', '=', 'access_path.id')
            ->where('group_id', $group_id)
            ->where('link', $controller)
            ->first();

        if (empty($groupAccess)) { // user doesn't have access
            $landingPage = GroupPath::where('id', $group_id)->first();
            
            // Prevent redirect loop - if already on landing page, just deny access
            if ($request->route()->getName() === $landingPage->landing_page) {
                abort(403, 'Anda tidak memiliki akses ke halaman ini');
            }
            
            return redirect()->route($landingPage->landing_page)->with('error', 'Anda tidak memiliki akses ke halaman tersebut');
        }

        // Refresh session expiry on successful access
        session(['expires_in' => time() + 3600]);

        return $next($request);
    }
}
