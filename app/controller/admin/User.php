<?php

namespace app\controller\admin;

use app\model\Order;
use app\model\User as UserModel;
use think\exception\HttpException;

class User extends AdminBase
{
    public function index()
    {
        $keyword = trim((string) $this->request->get('q', ''));
        $role = (string) $this->request->get('role', 'all');
        $status = (string) $this->request->get('status', 'all');

        $query = UserModel::order('id', 'desc');
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%')
                    ->whereOr('email', 'like', '%' . $keyword . '%')
                    ->whereOr('phone', 'like', '%' . $keyword . '%');
            });
        }
        if (in_array($role, ['admin', 'customer'], true)) {
            $query->where('role', $role);
        }
        if ($status === '1' || $status === '0') {
            $query->where('status', (int) $status);
        }

        $users = $query->paginate([
            'list_rows' => 15,
            'query'     => $this->request->get(),
        ]);

        $orderCounts = [];
        $rows = Order::field('user_id, COUNT(*) AS total')->group('user_id')->select();
        foreach ($rows as $row) {
            $orderCounts[(int) $row['user_id']] = (int) $row['total'];
        }
        foreach ($users as $userItem) {
            $userItem->order_count = $orderCounts[(int) $userItem->id] ?? 0;
        }

        return $this->render('admin/user/index', [
            'users'       => $users,
            'keyword'     => $keyword,
            'role'        => $role,
            'status'      => $status,
            'orderCounts' => $orderCounts,
            'totalUsers'  => UserModel::where('role', 'customer')->count(),
        ]);
    }

    public function toggle(int $id)
    {
        $user = UserModel::find($id);
        if (!$user) {
            throw new HttpException(404, '用户不存在');
        }
        if ($user->id === $this->currentUser->id) {
            flash('error', '不能停用当前登录的管理员账号。');
            return redirect('/admin/users');
        }
        if ($user->role === 'admin') {
            flash('error', '不能停用其他管理员账号。');
            return redirect('/admin/users');
        }
        $user->status = $user->status ? 0 : 1;
        $user->save();
        flash('success', $user->status ? '账号已启用。' : '账号已停用。');
        return redirect('/admin/users');
    }
}
