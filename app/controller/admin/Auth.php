<?php

namespace app\controller\admin;

use app\BaseController;
use app\model\User;
use think\facade\Session;

class Auth extends BaseController
{
    public function login()
    {
        if ($this->currentUser && $this->currentUser->role === 'admin') {
            return redirect('/admin');
        }
        return $this->render('admin/auth/login', [
            'next' => $this->safeNext($this->request->get('next', ''), '/admin'),
        ]);
    }

    public function doLogin()
    {
        $email = strtolower(trim((string) $this->request->post('email', '')));
        $password = (string) $this->request->post('password', '');
        $next = $this->safeNext($this->request->post('next', ''), '/admin');
        if (!str_starts_with($next, '/admin')) {
            $next = '/admin';
        }

        $user = User::where('email', $email)->find();
        if (!$user || !password_verify($password, $user->password) || $user->role !== 'admin' || (int) $user->status !== 1) {
            flash('error', '管理员账号或密码不正确。');
            $this->keepOld(['email' => $email]);
            return redirect('/admin/login');
        }

        Session::regenerate();
        Session::set('user_id', $user->id);
        flash('success', '欢迎回来，' . $user->name . '。');
        return redirect($next);
    }

    public function logout()
    {
        Session::delete('user_id');
        Session::regenerate();
        flash('success', '已退出管理后台。');
        return redirect('/admin/login');
    }
}
