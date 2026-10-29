<?php

namespace app\controller\admin;

use app\BaseController;
use app\model\Order;
use app\model\Product;
use think\facade\View;

abstract class AdminBase extends BaseController
{
    protected function initialize(): void
    {
        parent::initialize();

        if (!$this->currentUser || $this->currentUser->role !== 'admin') {
            // 路由中间件会先行拦截，这里作为兜底。
            throw new \think\exception\HttpException(403, '无权访问后台');
        }

        View::assign([
            'adminUser'     => $this->currentUser,
            'pendingCount'  => Order::where('status', 'paid')->count(),
            'lowStockCount' => Product::where('status', 1)->where('stock', '<=', 10)->count(),
        ]);
    }
}
