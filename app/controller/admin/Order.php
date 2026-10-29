<?php

namespace app\controller\admin;

use app\model\Order as OrderModel;
use app\service\OrderService;
use RuntimeException;
use think\exception\HttpException;
use think\facade\Db;

class Order extends AdminBase
{
    public function index()
    {
        $status = (string) $this->request->get('status', 'all');
        $allowed = ['all', 'pending', 'paid', 'shipped', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            $status = 'all';
        }
        $keyword = trim((string) $this->request->get('q', ''));

        $query = OrderModel::with('user');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('order_no', 'like', '%' . $keyword . '%')
                    ->whereOr('receiver', 'like', '%' . $keyword . '%')
                    ->whereOr('phone', 'like', '%' . $keyword . '%');
            });
        }

        $orders = $query->order('id', 'desc')->paginate([
            'list_rows' => 15,
            'query'     => $this->request->get(),
        ]);

        $statusCounts = ['all' => 0, 'pending' => 0, 'paid' => 0, 'shipped' => 0, 'completed' => 0, 'cancelled' => 0];
        $rows = Db::name('orders')->field('status, COUNT(*) AS total')->group('status')->select();
        foreach ($rows as $row) {
            if (isset($statusCounts[$row['status']])) {
                $statusCounts[$row['status']] = (int) $row['total'];
            }
            $statusCounts['all'] += (int) $row['total'];
        }

        return $this->render('admin/order/index', [
            'orders'       => $orders,
            'status'       => $status,
            'keyword'      => $keyword,
            'statusCounts' => $statusCounts,
        ]);
    }

    public function detail(string $orderNo)
    {
        $order = OrderModel::with(['items', 'user'])->where('order_no', $orderNo)->find();
        if (!$order) {
            throw new HttpException(404, '订单不存在');
        }

        return $this->render('admin/order/detail', ['order' => $order]);
    }

    public function action(string $orderNo)
    {
        $order = OrderModel::with('items')->where('order_no', $orderNo)->find();
        if (!$order) {
            throw new HttpException(404, '订单不存在');
        }

        $action = (string) $this->request->post('action', '');
        $service = app(OrderService::class);

        try {
            switch ($action) {
                case 'pay':
                    $service->pay($order);
                    flash('success', '已确认收款，订单进入待发货。');
                    break;
                case 'ship':
                    $service->ship($order);
                    flash('success', '订单已标记为已发货。');
                    break;
                case 'complete':
                    $service->complete($order);
                    flash('success', '订单已标记为完成。');
                    break;
                case 'cancel':
                    $service->cancel($order);
                    flash('success', '订单已取消，库存已回补。');
                    break;
                default:
                    flash('error', '无效的订单操作。');
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        return redirect('/admin/orders/' . $orderNo);
    }
}
