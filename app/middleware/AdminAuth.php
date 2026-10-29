<?php

namespace app\middleware;

use Closure;
use think\Request;

class AdminAuth
{
    public function handle(Request $request, Closure $next)
    {
        $user = current_user();
        if (!$user || $user->role !== 'admin') {
            return redirect('/admin/login?next=' . rawurlencode($request->url()));
        }

        return $next($request);
    }
}
