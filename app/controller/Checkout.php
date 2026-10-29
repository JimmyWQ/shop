<?php

namespace app\controller;

use app\BaseController;
use app\model\Order;
use app\service\CartService;
use app\service\OrderService;
use RuntimeException;

class Checkout extends BaseController
{
    public function index()
    {
        $cart = app(CartService::class);
        $items = $cart->items();
        if (!$items) {
            flash('error', '购物车是空的，先挑选喜欢的商品吧。');
            return redirect('/cart');
        }

        $lastOrder = Order::where('user_id', $this->currentUser->id)->order('id', 'desc')->find();
        $prefill = [
            'receiver' => $lastOrder->receiver ?? $this->currentUser->name,
            'phone'    => $lastOrder->phone ?? $this->currentUser->phone,
            'province' => '',
            'city'     => '',
            'district' => '',
            'address'  => '',
        ];

        if ($lastOrder && $lastOrder->address) {
            $parts = preg_split('/\s+/u', trim($lastOrder->address), 4);
            if (count($parts) === 4) {
                [$prefill['province'], $prefill['city'], $prefill['district'], $prefill['address']] = $parts;
            }
        }

        return $this->render('checkout/index', [
            'items'    => $items,
            'totals'   => $cart->totals($items),
            'prefill'  => $prefill,
            'payments' => [
                'wechat' => ['label' => '微信支付', 'hint' => '演示环境，下单后模拟支付'],
                'alipay' => ['label' => '支付宝', 'hint' => '演示环境，下单后模拟支付'],
                'cod'    => ['label' => '货到付款', 'hint' => '签收时向配送员付款'],
            ],
        ]);
    }

    public function submit()
    {
        $data = [
            'receiver'       => trim((string) $this->request->post('receiver', '')),
            'phone'          => trim((string) $this->request->post('phone', '')),
            'province'       => trim((string) $this->request->post('province', '')),
            'city'           => trim((string) $this->request->post('city', '')),
            'district'       => trim((string) $this->request->post('district', '')),
            'address'        => trim((string) $this->request->post('address', '')),
            'payment_method' => (string) $this->request->post('payment_method', 'wechat'),
            'remark'         => trim((string) $this->request->post('remark', '')),
        ];

        $error = $this->validateCheckout($data);
        if ($error !== null) {
            flash('error', $error);
            $this->keepOld($data);
            return redirect('/checkout');
        }

        try {
            $order = app(OrderService::class)->create($this->currentUser, $data, app(CartService::class));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->keepOld($data);
            return redirect('/checkout');
        }

        flash('success', $order->status === 'pending'
            ? '订单已创建，请完成支付。'
            : '订单已创建，我们会尽快为你发货。');

        return redirect('/order/' . $order->order_no);
    }

    private function validateCheckout(array $data): ?string
    {
        if (mb_strlen($data['receiver']) < 2 || mb_strlen($data['receiver']) > 30) {
            return '请填写 2–30 个字符的收货人姓名。';
        }
        if (!preg_match('/^1[3-9]\d{9}$/', $data['phone'])) {
            return '请填写有效的 11 位手机号。';
        }
        foreach (['province' => '省份', 'city' => '城市', 'district' => '区县'] as $key => $label) {
            if ($data[$key] === '' || mb_strlen($data[$key]) > 30) {
                return '请填写' . $label . '。';
            }
        }
        if (mb_strlen($data['address']) < 3 || mb_strlen($data['address']) > 120) {
            return '请填写 3–120 个字符的详细地址。';
        }
        if (!in_array($data['payment_method'], ['wechat', 'alipay', 'cod'], true)) {
            return '请选择有效的支付方式。';
        }
        if (mb_strlen($data['remark']) > 200) {
            return '订单备注不能超过 200 个字符。';
        }
        return null;
    }
}
