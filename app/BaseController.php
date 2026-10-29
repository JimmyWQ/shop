<?php

declare(strict_types=1);

namespace app;

use app\model\Category;
use app\model\User;
use think\App;
use think\exception\ValidateException;
use think\facade\Session;
use think\facade\View;
use think\Validate;

/**
 * 控制器基础类：统一注入模板共享变量与常用响应方法。
 */
abstract class BaseController
{
    protected App $app;
    protected $request;
    protected bool $batchValidate = false;
    protected array $middleware = [];
    protected ?User $currentUser = null;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $app->request;
        $this->initialize();
    }

    protected function initialize(): void
    {
        $this->currentUser = current_user();
        $old = Session::pull('old_input');

        View::assign([
            'currentUser' => $this->currentUser,
            'cartCount'   => cart_count(),
            'csrfToken'   => csrf_token(),
            'flash'       => flash_pull(),
            'old'         => is_array($old) ? $old : [],
            'currentPath' => '/' . trim($this->request->pathinfo(), '/'),
            'siteName'    => 'NOVA',
            'navCategories' => Category::where('status', 1)->order('sort', 'asc')->limit(6)->select(),
        ]);
    }

    /**
     * 渲染应用视图。
     */
    protected function render(string $template, array $vars = [])
    {
        return view($template, $vars);
    }

    /**
     * 记录表单旧值，便于校验失败时回填。
     */
    protected function keepOld(array $data): void
    {
        unset($data['_token'], $data['password'], $data['password_confirm']);
        Session::set('old_input', $data);
    }

    /**
     * 带回旧值与错误信息跳转。
     */
    protected function backWithError(string $message, array $old = [], string $fallback = '/')
    {
        if ($old) {
            $this->keepOld($old);
        }
        flash('error', $message);
        return redirect($fallback);
    }

    /**
     * 是否期望 JSON 响应。
     */
    protected function wantsJson(): bool
    {
        return $this->request->isAjax()
            || str_contains(strtolower((string) $this->request->header('accept')), 'application/json');
    }

    protected function jsonOk(array $data = [], string $message = '操作成功')
    {
        return json(array_merge(['ok' => true, 'message' => $message], $data));
    }

    protected function jsonFail(string $message, int $status = 422, array $data = [])
    {
        return json(array_merge(['ok' => false, 'message' => $message], $data), $status);
    }

    /**
     * 仅允许站内相对地址，防止开放重定向。
     */
    protected function safeNext(?string $next, string $fallback = '/account'): string
    {
        $next = trim((string) $next);
        if ($next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//')) {
            return $fallback;
        }
        return $next;
    }

    /**
     * ThinkPHP 内置验证器封装。
     */
    protected function validate(array $data, string|array $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (str_contains($validate, '.')) {
                [$validate, $scene] = explode('.', $validate, 2);
            }
            $class = str_contains($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        return $v->failException(true)->check($data);
    }
}
