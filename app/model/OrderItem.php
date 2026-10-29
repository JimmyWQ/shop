<?php

namespace app\model;

use think\Model;

class OrderItem extends Model
{
    protected $name = 'order_items';
    protected $autoWriteTimestamp = false;
    protected $type = [
        'order_id'   => 'integer',
        'product_id' => 'integer',
        'price'      => 'float',
        'quantity'   => 'integer',
        'subtotal'   => 'float',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
