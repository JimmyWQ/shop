<?php

namespace app\model;

use think\Model;

class User extends Model
{
    protected $name = 'users';
    protected $autoWriteTimestamp = 'datetime';
    protected $hidden = ['password'];
    protected $type = [
        'status' => 'integer',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id');
    }
}
