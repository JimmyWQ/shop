<?php

namespace app\middleware;

use Closure;
use think\Request;

class Auth
{
    public function handle(Request $request, Closure $next)
    {
        if (!current_user()) {
            $next = $request->url();
            return redirect('/login?next=' . rawurlencode($next));
        }

        return $next($request);
    }
}
