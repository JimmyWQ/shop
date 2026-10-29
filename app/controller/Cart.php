<?php

namespace app\controller;

use app\BaseController;
use app\model\Product;
use app\service\CartService;
use RuntimeException;

class Cart extends BaseController
{
    private function cart(): CartService
    {
        return app(CartService::class);
    }

    public function index()
    {
        $items = $this->cart()->items();
        $totals = $this->cart()->totals($items);
        $recommended = Product::with('category')
            ->where('status', 1)
            ->order('sales', 'desc')
            ->limit(3)
            ->select();

        return $this->render('cart/index', [
            'items'       => $items,
            'totals'      => $totals,
            'recommended' => $recommended,
        ]);
    }

    public function add()
    {
        $productId = (int) $this->request->post('product_id', 0);
        $quantity = (int) $this->request->post('quantity', 1);
        $next = (string) $this->request->post('next', '');

        try {
            $result = $this->cart()->add($productId, $quantity);
            if ($this->wantsJson()) {
                return $this->jsonOk([
                    'count'   => $result['count'],
                    'message' => $result['message'],
                ], $result['message']);
            }
            flash('success', $result['message']);
        } catch (RuntimeException $e) {
            if ($this->wantsJson()) {
                return $this->jsonFail($e->getMessage());
            }
            flash('error', $e->getMessage());
        }

        if ($next === 'checkout') {
            return redirect('/checkout');
        }
        return redirect('/cart');
    }

    public function update()
    {
        $productId = (int) $this->request->post('product_id', 0);
        $quantity = (int) $this->request->post('quantity', 0);

        try {
            $result = $this->cart()->update($productId, $quantity);
            $items = $this->cart()->items();
            $totals = $this->cart()->totals($items);

            if ($this->wantsJson()) {
                return $this->jsonOk([
                    'count'    => $result['count'],
                    'quantity' => $result['quantity'] ?? 0,
                    'subtotal' => $result['subtotal'] ?? 0,
                    'removed'  => $result['removed'] ?? false,
                    'totals'   => $totals,
                ]);
            }
            flash('success', '购物车已更新。');
        } catch (RuntimeException $e) {
            if ($this->wantsJson()) {
                return $this->jsonFail($e->getMessage());
            }
            flash('error', $e->getMessage());
        }

        return redirect('/cart');
    }

    public function remove()
    {
        $productId = (int) $this->request->post('product_id', 0);

        try {
            $result = $this->cart()->remove($productId);
            if ($this->wantsJson()) {
                return $this->jsonOk([
                    'count'  => $result['count'],
                    'totals' => $this->cart()->totals(),
                ], '已移出购物车。');
            }
            flash('success', '已移出购物车。');
        } catch (RuntimeException $e) {
            if ($this->wantsJson()) {
                return $this->jsonFail($e->getMessage());
            }
            flash('error', $e->getMessage());
        }

        return redirect('/cart');
    }

    public function clear()
    {
        $this->cart()->clear();
        flash('success', '购物车已清空。');
        return redirect('/cart');
    }
}
