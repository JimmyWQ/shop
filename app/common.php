<?php

use app\model\User;
use app\service\CartService;
use think\facade\Session;

if (!function_exists('current_user')) {
    /**
     * 当前登录用户（单次请求内缓存）。
     */
    function current_user(): ?User
    {
        static $loaded = false;
        static $user = null;

        if ($loaded) {
            return $user;
        }

        $loaded = true;
        $id = (int) Session::get('user_id', 0);
        if ($id <= 0) {
            return null;
        }

        $user = User::find($id);
        if (!$user || (int) $user->status !== 1) {
            Session::delete('user_id');
            $user = null;
        }

        return $user;
    }
}

if (!function_exists('is_admin')) {
    function is_admin(): bool
    {
        $user = current_user();
        return $user !== null && $user->role === 'admin';
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::set('flash', ['type' => $type, 'message' => $message]);
    }
}

if (!function_exists('flash_pull')) {
    function flash_pull(): ?array
    {
        $flash = Session::pull('flash');
        return is_array($flash) ? $flash : null;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        $token = Session::get('_csrf');
        if (!$token) {
            $token = bin2hex(random_bytes(32));
            Session::set('_csrf', $token);
        }
        return $token;
    }
}

if (!function_exists('avatar_text')) {
    function avatar_text(?string $name): string
    {
        $name = trim((string) $name);
        return $name === '' ? 'N' : mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
    }
}

if (!function_exists('money')) {
    function money($amount): string
    {
        return '¥' . number_format((float) $amount, 2);
    }
}

if (!function_exists('order_status_label')) {
    function order_status_label(string $status): string
    {
        return [
            'pending'   => '待付款',
            'paid'      => '待发货',
            'shipped'   => '待收货',
            'completed' => '已完成',
            'cancelled' => '已取消',
        ][$status] ?? $status;
    }
}

if (!function_exists('order_status_tone')) {
    function order_status_tone(string $status): string
    {
        return [
            'pending'   => 'warning',
            'paid'      => 'info',
            'shipped'   => 'accent',
            'completed' => 'success',
            'cancelled' => 'muted',
        ][$status] ?? 'muted';
    }
}

if (!function_exists('payment_label')) {
    function payment_label(?string $method): string
    {
        return [
            'wechat' => '微信支付',
            'alipay' => '支付宝',
            'cod'    => '货到付款',
        ][$method] ?? '在线支付';
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, string $format = 'Y.m.d'): string
    {
        if (!$date) {
            return '—';
        }
        $time = strtotime($date);
        return $time ? date($format, $time) : '—';
    }
}

if (!function_exists('product_cover')) {
    function product_cover(?string $cover): string
    {
        $cover = trim((string) $cover);
        if ($cover === '') {
            return '/static/images/products/placeholder.svg';
        }
        if (preg_match('#^https?://#i', $cover)) {
            return $cover;
        }
        return '/' . ltrim($cover, '/');
    }
}

if (!function_exists('form_value')) {
    /**
     * 表单回填值：优先旧输入，其次预填，并做好属性转义。
     */
    function form_value(array $old, string $key, $default = ''): string
    {
        $value = array_key_exists($key, $old) && $old[$key] !== '' ? $old[$key] : $default;
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('cart_count')) {
    function cart_count(): int
    {
        return app(CartService::class)->count();
    }
}

if (!function_exists('is_active')) {
    function is_active(string $path, string $prefix = ''): bool
    {
        if ($prefix === '') {
            return $path === '/';
        }
        return $path === $prefix || str_starts_with($path, rtrim($prefix, '/') . '/');
    }
}

if (!function_exists('order_actions')) {
    function order_actions(string $status): array
    {
        return [
            'pending'   => ['pay', 'cancel'],
            'paid'      => ['cancel'],
            'shipped'   => ['confirm'],
            'completed' => [],
            'cancelled' => [],
        ][$status] ?? [];
    }
}
