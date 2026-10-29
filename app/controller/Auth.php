<?php

namespace app\controller;

use app\BaseController;
use app\model\User;
use think\facade\Session;

class Auth extends BaseController
{
    public function login()
    {
        if ($this->currentUser) {
            return redirect('/account');
        }
        return $this->render('auth/login', [
            'next' => $this->safeNext($this->request->get('next', ''), '/account'),
        ]);
    }

    public function doLogin()
    {
        $email = strtolower(trim((string) $this->request->post('email', '')));
        $password = (string) $this->request->post('password', '');
        $next = $this->safeNext($this->request->post('next', ''), '/account');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            flash('error', '请输入有效的邮箱和密码。');
            $this->keepOld(['email' => $email]);
            return redirect('/login?next=' . rawurlencode($next));
        }

        $user = User::where('email', $email)->find();
        if (!$user || !password_verify($password, $user->password)) {
            flash('error', '邮箱或密码不正确。');
            $this->keepOld(['email' => $email]);
            return redirect('/login?next=' . rawurlencode($next));
        }
        if ((int) $user->status !== 1) {
            flash('error', '该账号已被停用，请联系客服。');
            return redirect('/login');
        }

        if (password_needs_rehash($user->password, PASSWORD_DEFAULT)) {
            $user->password = password_hash($password, PASSWORD_DEFAULT);
            $user->save();
        }

        Session::regenerate();
        Session::set('user_id', $user->id);
        flash('success', '欢迎回来，' . $user->name . '。');
        return redirect($next);
    }

    public function register()
    {
        if ($this->currentUser) {
            return redirect('/account');
        }
        return $this->render('auth/register');
    }

    public function doRegister()
    {
        $name = trim((string) $this->request->post('name', ''));
        $email = strtolower(trim((string) $this->request->post('email', '')));
        $phone = trim((string) $this->request->post('phone', ''));
        $password = (string) $this->request->post('password', '');
        $confirm = (string) $this->request->post('password_confirm', '');

        $old = ['name' => $name, 'email' => $email, 'phone' => $phone];

        if (mb_strlen($name) < 2 || mb_strlen($name) > 24) {
            flash('error', '昵称长度需在 2–24 个字符之间。');
            $this->keepOld($old);
            return redirect('/register');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
            flash('error', '请输入有效的邮箱地址。');
            $this->keepOld($old);
            return redirect('/register');
        }
        if ($phone !== '' && !preg_match('/^1[3-9]\d{9}$/', $phone)) {
            flash('error', '手机号格式不正确。');
            $this->keepOld($old);
            return redirect('/register');
        }
        if (strlen($password) < 8 || !preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            flash('error', '密码至少 8 位，且需同时包含字母和数字。');
            $this->keepOld($old);
            return redirect('/register');
        }
        if ($password !== $confirm) {
            flash('error', '两次输入的密码不一致。');
            $this->keepOld($old);
            return redirect('/register');
        }
        if (User::where('email', $email)->count() > 0) {
            flash('error', '这个邮箱已经注册过了，可以直接登录。');
            $this->keepOld($old);
            return redirect('/register');
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $email,
            'phone'    => $phone,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role'     => 'customer',
            'status'   => 1,
        ]);

        Session::regenerate();
        Session::set('user_id', $user->id);
        flash('success', '账号创建成功，欢迎加入 NOVA。');
        return redirect('/account');
    }

    public function logout()
    {
        Session::delete('user_id');
        Session::regenerate();
        flash('success', '你已退出登录。');
        return redirect('/');
    }
}
