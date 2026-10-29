<?php

namespace app\controller;

use app\BaseController;
use app\model\Order as OrderModel;
use app\service\OrderService;
use RuntimeException;
use think\exception\HttpException;

class Order extends BaseController
{
    public function show(string $orderNo)
    {
        $order = $this->findOrder($orderNo);

        $steps = [
            ['label' => '提交订单', 'time' => $order->create_time, 'done' => true],
            ['label' => '完成支付', 'time' => $order->pay_time, 'done' => in_array($order->status, ['paid', 'shipped', 'completed'], true) || $order->payment_method === 'cod'],
            ['label' => '商家发货', 'time' => $order->ship_time, 'done' => in_array($order->status, ['shipped', 'completed'], true)],
            ['label' => '确认收货', 'time' => $order->complete_time, 'done' => $order->status === 'completed'],
        ];

        return $this->render('order/detail', [
            'order'     => $order,
            'items'     => $order->items,
            'steps'     => $steps,
            'canPay'    => $order->status === 'pending' && $order->payment_method !== 'cod',
            'canCancel' => in_array($order->status, ['pending', 'paid'], true),
            'canConfirm' => $order->status === 'shipped',
        ]);
    }

    public function pay(string $orderNo)
    {
        $order = $this->findOrder($orderNo);
        try {
            app(OrderService::class)->pay($order);
            flash('success', '支付成功，订单已进入待发货状态。');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        return redirect('/order/' . $orderNo);
    }

    public function cancel(string $orderNo)
    {
        $order = $this->findOrder($orderNo);
        try {
            app(OrderService::class)->cancel($order);
            flash('success', '订单已取消，库存已回补。');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        return redirect('/order/' . $orderNo);
    }

    public function complete(string $orderNo)
    {
        $order = $this->findOrder($orderNo);
        try {
            app(OrderService::class)->complete($order);
            flash('success', '已确认收货，感谢你的选择。');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        return redirect('/order/' . $orderNo);
    }

    private function findOrder(string $orderNo): OrderModel
    {
        $order = OrderModel::with('items')
            ->where('order_no', $orderNo)
            ->where('user_id', $this->currentUser->id)
            ->find();
        if (!$order) {
            throw new HttpException(404, '订单不存在');
        }
        return $order;
    }
}
