<?php

namespace app\service;

use app\model\Order;
use app\model\User;
use RuntimeException;
use think\facade\Db;

class OrderService
{
    /**
     * 根据购物车创建订单：锁定库存、写入快照、扣减库存。
     */
    public function create(User $user, array $data, CartService $cart): Order
    {
        $items = $cart->items();
        if (!$items) {
            throw new RuntimeException('购物车是空的，先去挑选商品吧。');
        }

        $now = date('Y-m-d H:i:s');
        $orderNo = $this->makeOrderNo();
        $isCod = ($data['payment_method'] ?? '') === 'cod';

        Db::startTrans();
        try {
            $lines = [];
            $subtotal = 0.0;
            $quantity = 0;

            foreach ($items as $item) {
                $productId = (int) $item['product']->id;
                $product = Db::name('products')->where('id', $productId)->find();
                if (!$product || (int) $product['status'] !== 1) {
                    throw new RuntimeException('「' . $item['product']->name . '」已下架，请返回购物车调整。');
                }
                $wanted = (int) $item['quantity'];
                if ((int) $product['stock'] < $wanted) {
                    throw new RuntimeException('「' . $product['name'] . '」库存不足，仅剩 ' . (int) $product['stock'] . ' 件。');
                }

                $price = round((float) $product['price'], 2);
                $lineSubtotal = round($price * $wanted, 2);
                $subtotal += $lineSubtotal;
                $quantity += $wanted;

                $affected = Db::name('products')
                    ->where('id', $productId)
                    ->where('status', 1)
                    ->where('stock', '>=', $wanted)
                    ->update([
                        'stock'       => Db::raw('stock - ' . $wanted),
                        'sales'       => Db::raw('sales + ' . $wanted),
                        'update_time' => $now,
                    ]);
                if ($affected !== 1) {
                    throw new RuntimeException('「' . $product['name'] . '」库存刚刚发生变化，请重试。');
                }

                $lines[] = [
                    'product_id'    => $productId,
                    'product_name'  => $product['name'],
                    'product_cover' => $product['cover'],
                    'price'         => $price,
                    'quantity'      => $wanted,
                    'subtotal'      => $lineSubtotal,
                    'create_time'   => $now,
                ];
            }

            $subtotal = round($subtotal, 2);
            $shipping = ($subtotal > 0 && $subtotal < CartService::FREE_SHIPPING_THRESHOLD)
                ? CartService::SHIPPING_FEE
                : 0.0;
            $total = round($subtotal + $shipping, 2);
            $address = trim(($data['province'] ?? '') . ' ' . ($data['city'] ?? '') . ' ' . ($data['district'] ?? '') . ' ' . ($data['address'] ?? ''));

            $orderId = Db::name('orders')->insertGetId([
                'order_no'       => $orderNo,
                'user_id'        => $user->id,
                'status'         => $isCod ? 'paid' : 'pending',
                'payment_method' => $data['payment_method'],
                'subtotal'       => $subtotal,
                'shipping_fee'   => $shipping,
                'total_amount'   => $total,
                'item_count'     => $quantity,
                'receiver'       => $data['receiver'],
                'phone'          => $data['phone'],
                'address'        => $address,
                'remark'         => $data['remark'] ?? '',
                'pay_time'       => $isCod ? $now : null,
                'create_time'    => $now,
                'update_time'    => $now,
            ]);

            foreach ($lines as &$line) {
                $line['order_id'] = $orderId;
            }
            unset($line);
            Db::name('order_items')->insertAll($lines);

            Db::commit();
            $cart->clear();

            return Order::with('items')->find($orderId);
        } catch (\Throwable $e) {
            Db::rollback();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('订单创建失败，请稍后重试。', 0, $e);
        }
    }

    public function pay(Order $order): Order
    {
        if ($order->status !== 'pending') {
            throw new RuntimeException('该订单当前不需要支付。');
        }
        if ($order->payment_method === 'cod') {
            throw new RuntimeException('货到付款订单无需在线支付。');
        }

        $affected = Db::name('orders')
            ->where('id', $order->id)
            ->where('status', 'pending')
            ->update([
                'status'      => 'paid',
                'pay_time'    => date('Y-m-d H:i:s'),
                'update_time' => date('Y-m-d H:i:s'),
            ]);
        if ($affected !== 1) {
            throw new RuntimeException('订单状态已变化，请刷新查看。');
        }
        return Order::find($order->id);
    }

    public function ship(Order $order): Order
    {
        if ($order->status !== 'paid') {
            throw new RuntimeException('只有待发货订单可以发货。');
        }

        $now = date('Y-m-d H:i:s');
        $affected = Db::name('orders')
            ->where('id', $order->id)
            ->where('status', 'paid')
            ->update(['status' => 'shipped', 'ship_time' => $now, 'update_time' => $now]);
        if ($affected !== 1) {
            throw new RuntimeException('订单状态已变化，请刷新查看。');
        }
        return Order::find($order->id);
    }

    public function complete(Order $order): Order
    {
        if ($order->status !== 'shipped') {
            throw new RuntimeException('只有待收货订单可以确认完成。');
        }

        $now = date('Y-m-d H:i:s');
        $affected = Db::name('orders')
            ->where('id', $order->id)
            ->where('status', 'shipped')
            ->update(['status' => 'completed', 'complete_time' => $now, 'update_time' => $now]);
        if ($affected !== 1) {
            throw new RuntimeException('订单状态已变化，请刷新查看。');
        }
        return Order::find($order->id);
    }

    /**
     * 取消订单并回补库存。已发货/已完成订单不可取消。
     */
    public function cancel(Order $order): Order
    {
        if (!in_array($order->status, ['pending', 'paid'], true)) {
            throw new RuntimeException('该订单当前不可取消。');
        }

        Db::startTrans();
        try {
            $now = date('Y-m-d H:i:s');
            $affected = Db::name('orders')
                ->where('id', $order->id)
                ->whereIn('status', ['pending', 'paid'])
                ->update(['status' => 'cancelled', 'cancel_time' => $now, 'update_time' => $now]);

            if ($affected !== 1) {
                throw new RuntimeException('订单状态已变化，请刷新查看。');
            }

            $items = Db::name('order_items')->where('order_id', $order->id)->select();
            foreach ($items as $item) {
                Db::name('products')->where('id', $item['product_id'])->update([
                    'stock'       => Db::raw('stock + ' . (int) $item['quantity']),
                    'update_time' => $now,
                ]);
            }

            Db::commit();
            return Order::find($order->id);
        } catch (\Throwable $e) {
            Db::rollback();
            if ($e instanceof RuntimeException) {
                throw $e;
            }
            throw new RuntimeException('取消订单失败，请稍后重试。', 0, $e);
        }
    }

    public function makeOrderNo(): string
    {
        for ($i = 0; $i < 5; $i++) {
            $no = date('ymdHis') . str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            if (!Db::name('orders')->where('order_no', $no)->count()) {
                return $no;
            }
        }
        return date('ymdHis') . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT);
    }
}
