<?php

namespace app\model;

use think\Model;

class Order extends Model
{
    protected $name = 'orders';
    protected $autoWriteTimestamp = 'datetime';
    protected $type = [
        'user_id'       => 'integer',
        'total_amount'  => 'float',
        'shipping_fee'  => 'float',
        'item_count'    => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
