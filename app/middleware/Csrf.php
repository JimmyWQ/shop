<?php

namespace app\middleware;

use Closure;
use think\facade\Session;
use think\facade\View;
use think\Request;

/**
 * 为所有页面签发 CSRF Token，并校验写请求。
 */
class Csrf
{
    public function handle(Request $request, Closure $next)
    {
        $token = Session::get('_csrf');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf', $token);
        }

        View::assign('csrfToken', $token);

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $submitted = (string) $request->post('_token', $request->header('X-CSRF-TOKEN', ''));
            if ($submitted === '' || !hash_equals((string) $token, $submitted)) {
                if ($request->isAjax() || str_contains(strtolower((string) $request->header('accept')), 'application/json')) {
                    return json(['ok' => false, 'message' => '页面已过期，请刷新后重试。'], 419);
                }
                flash('error', '页面已过期，请刷新后重试。');
                return redirect('/');
            }
        }

        return $next($request);
    }
}
