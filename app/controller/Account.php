<?php

namespace app\controller;

use app\BaseController;
use app\model\Order;
use think\facade\Db;

class Account extends BaseController
{
    public function index()
    {
        $userId = $this->currentUser->id;
        $recent = Order::with('items')
            ->where('user_id', $userId)
            ->order('id', 'desc')
            ->limit(3)
            ->select();

        $statusCounts = $this->statusCounts($userId);
        $spent = Order::where('user_id', $userId)
            ->whereIn('status', ['paid', 'shipped', 'completed'])
            ->sum('total_amount');

        return $this->render('account/index', [
            'recent'       => $recent,
            'statusCounts' => $statusCounts,
            'orderCount'   => $statusCounts['all'],
            'pendingTotal' => $statusCounts['pending'] + $statusCounts['paid'] + $statusCounts['shipped'],
            'spent'        => $spent,
        ]);
    }

    public function orders()
    {
        $userId = $this->currentUser->id;
        $status = (string) $this->request->get('status', 'all');
        $allowed = ['all', 'pending', 'paid', 'shipped', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            $status = 'all';
        }

        $query = Order::with('items')->where('user_id', $userId);
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $orders = $query->order('id', 'desc')->paginate([
            'list_rows' => 8,
            'query'     => $this->request->get(),
        ]);

        $statusCounts = $this->statusCounts($userId);
        return $this->render('account/orders', [
            'orders'       => $orders,
            'status'       => $status,
            'statusCounts' => $statusCounts,
            'pendingTotal' => $statusCounts['pending'] + $statusCounts['paid'] + $statusCounts['shipped'],
        ]);
    }

    public function updateProfile()
    {
        $name = trim((string) $this->request->post('name', ''));
        $phone = trim((string) $this->request->post('phone', ''));

        if (mb_strlen($name) < 2 || mb_strlen($name) > 24) {
            flash('error', '昵称长度需在 2–24 个字符之间。');
            return redirect('/account');
        }
        if ($phone !== '' && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
            flash('error', '手机号格式不正确。');
            return redirect('/account');
        }

        $this->currentUser->name = $name;
        $this->currentUser->phone = $phone;
        $this->currentUser->save();
        flash('success', '个人资料已更新。');
        return redirect('/account');
    }

    public function updatePassword()
    {
        $current = (string) $this->request->post('current_password', '');
        $password = (string) $this->request->post('password', '');
        $confirm = (string) $this->request->post('password_confirm', '');

        if (!password_verify($current, $this->currentUser->password)) {
            flash('error', '当前密码不正确。');
            return redirect('/account');
        }
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            flash('error', '新密码至少 8 位，且需同时包含字母和数字。');
            return redirect('/account');
        }
        if ($password !== $confirm) {
            flash('error', '两次输入的新密码不一致。');
            return redirect('/account');
        }

        $this->currentUser->password = password_hash($password, PASSWORD_DEFAULT);
        $this->currentUser->save();
        flash('success', '密码已更新，请记住新的登录密码。');
        return redirect('/account');
    }

    private function statusCounts(int $userId): array
    {
        $counts = [
            'all'       => 0,
            'pending'   => 0,
            'paid'      => 0,
            'shipped'   => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        $rows = Db::name('orders')
            ->field('status, COUNT(*) AS total')
            ->where('user_id', $userId)
            ->group('status')
            ->select();
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $total = (int) $row['total'];
            if (isset($counts[$status])) {
                $counts[$status] = $total;
            }
            $counts['all'] += $total;
        }
        return $counts;
    }
}
