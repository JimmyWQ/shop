<?php

namespace app\controller\admin;

use app\model\Order;
use app\model\Product;
use app\model\User;
use think\facade\Db;

class Dashboard extends AdminBase
{
    public function index()
    {
        $revenueStatuses = ['paid', 'shipped', 'completed'];
        $revenue = Order::whereIn('status', $revenueStatuses)->sum('total_amount');
        $paidOrderCount = Order::whereIn('status', $revenueStatuses)->count();
        $orderCount = Order::count();
        $todayStart = date('Y-m-d 00:00:00');
        $todayRevenue = Order::whereIn('status', $revenueStatuses)
            ->where('create_time', '>=', $todayStart)
            ->sum('total_amount');
        $customerCount = User::where('role', 'customer')->count();
        $productCount = Product::count();
        $lowStock = Product::where('status', 1)->where('stock', '<=', 10)->order('stock', 'asc')->limit(5)->select();

        $statusCounts = [
            'pending'   => 0,
            'paid'      => 0,
            'shipped'   => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];
        $rows = Db::name('orders')->field('status, COUNT(*) AS total')->group('status')->select();
        foreach ($rows as $row) {
            if (isset($statusCounts[$row['status']])) {
                $statusCounts[$row['status']] = (int) $row['total'];
            }
        }

        $recent = Order::with('user')->order('id', 'desc')->limit(6)->select();
        $topProducts = Product::order('sales', 'desc')->limit(5)->select();

        $chartRows = Order::where('create_time', '>=', date('Y-m-d 00:00:00', strtotime('-6 days')))
            ->where('status', '<>', 'cancelled')
            ->field('create_time, total_amount')
            ->select();
        $chart = [];
        $maxRevenue = 1;
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime('-' . $i . ' days'));
            $chart[$date] = ['label' => date('m.d', strtotime($date)), 'revenue' => 0.0, 'orders' => 0];
        }
        foreach ($chartRows as $row) {
            $date = substr((string) $row['create_time'], 0, 10);
            if (isset($chart[$date])) {
                $chart[$date]['revenue'] += (float) $row['total_amount'];
                $chart[$date]['orders']++;
                $maxRevenue = max($maxRevenue, $chart[$date]['revenue']);
            }
        }

        return $this->render('admin/dashboard', [
            'revenue'       => $revenue,
            'todayRevenue'  => $todayRevenue,
            'orderCount'    => $orderCount,
            'customerCount' => $customerCount,
            'productCount'  => $productCount,
            'lowStock'      => $lowStock,
            'statusCounts'  => $statusCounts,
            'recent'        => $recent,
            'topProducts'   => $topProducts,
            'chart'         => $chart,
            'maxRevenue'    => $maxRevenue,
            'avgOrder'      => $paidOrderCount > 0 ? $revenue / $paidOrderCount : 0,
        ]);
    }
}
