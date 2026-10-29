<?php

namespace app\model;

use think\Model;

class Category extends Model
{
    protected $name = 'categories';
    protected $autoWriteTimestamp = 'datetime';
    protected $type = [
        'sort'   => 'integer',
        'status' => 'integer',
    ];

    public function products()
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
