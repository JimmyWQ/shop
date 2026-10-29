<?php

namespace app\service;

use app\model\Product;
use RuntimeException;
use think\facade\Session;

/**
 * 会话购物车：游客与登录用户共用，结算时生成订单快照。
 */
class CartService
{
    public const FREE_SHIPPING_THRESHOLD = 299.00;
    public const SHIPPING_FEE = 15.00;
    private const SESSION_KEY = 'cart';

    public function raw(): array
    {
        $cart = Session::get(self::SESSION_KEY, []);
        if (!is_array($cart)) {
            return [];
        }

        $clean = [];
        foreach ($cart as $id => $quantity) {
            $id = (int) $id;
            $quantity = (int) $quantity;
            if ($id > 0 && $quantity > 0) {
                $clean[$id] = min($quantity, 99);
            }
        }
        return $clean;
    }

    /**
     * @return array<int, array{product: Product, quantity: int, subtotal: float}>
     */
    public function items(): array
    {
        $cart = $this->raw();
        if (!$cart) {
            return [];
        }

        $products = Product::whereIn('id', array_keys($cart))
            ->where('status', 1)
            ->select();
        $map = [];
        foreach ($products as $product) {
            $map[(int) $product->id] = $product;
        }

        $items = [];
        foreach ($cart as $id => $quantity) {
            $product = $map[$id] ?? null;
            if (!$product || (int) $product->stock <= 0) {
                unset($cart[$id]);
                continue;
            }
            $quantity = min($quantity, (int) $product->stock);
            $cart[$id] = $quantity;
            $items[$id] = [
                'product'  => $product,
                'quantity' => $quantity,
                'subtotal' => round((float) $product->price * $quantity, 2),
            ];
        }

        Session::set(self::SESSION_KEY, $cart);
        return $items;
    }

    public function add(int $productId, int $quantity = 1): array
    {
        $quantity = max(1, min(99, $quantity));
        /** @var Product|null $product */
        $product = Product::where('id', $productId)->where('status', 1)->find();
        if (!$product) {
            throw new RuntimeException('这件商品已下架。');
        }
        if ((int) $product->stock <= 0) {
            throw new RuntimeException('这件商品暂时缺货。');
        }

        $cart = $this->raw();
        $current = $cart[$productId] ?? 0;
        $target = min($current + $quantity, (int) $product->stock, 99);
        $cart[$productId] = $target;
        Session::set(self::SESSION_KEY, $cart);

        $capped = $target < $current + $quantity;
        return [
            'count'   => array_sum($cart),
            'message' => $capped ? '已按库存上限加入购物车。' : '已加入购物车。',
        ];
    }

    public function update(int $productId, int $quantity): array
    {
        $cart = $this->raw();
        if (!isset($cart[$productId])) {
            throw new RuntimeException('购物车中没有这件商品。');
        }

        if ($quantity <= 0) {
            unset($cart[$productId]);
            Session::set(self::SESSION_KEY, $cart);
            return ['removed' => true, 'count' => array_sum($cart)];
        }

        /** @var Product|null $product */
        $product = Product::where('id', $productId)->where('status', 1)->find();
        if (!$product) {
            unset($cart[$productId]);
            Session::set(self::SESSION_KEY, $cart);
            throw new RuntimeException('商品已下架，已从购物车移除。');
        }

        $quantity = min($quantity, (int) $product->stock, 99);
        if ($quantity <= 0) {
            unset($cart[$productId]);
            Session::set(self::SESSION_KEY, $cart);
            throw new RuntimeException('商品暂时缺货，已从购物车移除。');
        }

        $cart[$productId] = $quantity;
        Session::set(self::SESSION_KEY, $cart);

        return [
            'quantity' => $quantity,
            'subtotal' => round((float) $product->price * $quantity, 2),
            'count'    => array_sum($cart),
        ];
    }

    public function remove(int $productId): array
    {
        $cart = $this->raw();
        unset($cart[$productId]);
        Session::set(self::SESSION_KEY, $cart);
        return ['count' => array_sum($cart)];
    }

    public function clear(): void
    {
        Session::delete(self::SESSION_KEY);
    }

    public function count(): int
    {
        return (int) array_sum($this->raw());
    }

    public function totals(?array $items = null): array
    {
        $items ??= $this->items();
        $subtotal = 0.0;
        $quantity = 0;
        foreach ($items as $item) {
            $subtotal += $item['subtotal'];
            $quantity += $item['quantity'];
        }
        $subtotal = round($subtotal, 2);
        $shipping = ($subtotal > 0 && $subtotal < self::FREE_SHIPPING_THRESHOLD)
            ? self::SHIPPING_FEE
            : 0.0;

        return [
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total'    => round($subtotal + $shipping, 2),
            'free_gap' => max(0, round(self::FREE_SHIPPING_THRESHOLD - $subtotal, 2)),
        ];
    }
}
