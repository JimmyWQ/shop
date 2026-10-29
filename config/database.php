<?php

return [
    'default' => env('DB_DRIVER', 'sqlite'),

    'time_query_rule' => [],
    'auto_timestamp'  => true,
    'datetime_format' => 'Y-m-d H:i:s',
    'datetime_field'  => '',

    'connections' => [
        'sqlite' => [
            'type'          => 'sqlite',
            'database'      => (static function () {
                $database = (string) env('DB_NAME', root_path() . 'database/mall.sqlite');
                if (!preg_match('#^([A-Za-z]:[\\\\/]|/)#', $database)) {
                    $database = root_path() . ltrim($database, '/\\');
                }
                return $database;
            })(),
            'prefix'        => '',
            'charset'       => 'utf8',
            'trigger_sql'   => env('APP_DEBUG', false),
            'fields_cache'  => false,
            'fields_strict' => false,
        ],

        'mysql' => [
            'type'            => 'mysql',
            'hostname'        => env('DB_HOST', '127.0.0.1'),
            'database'        => env('DB_NAME', 'thinkmall'),
            'username'        => env('DB_USER', 'root'),
            'password'        => env('DB_PASS', ''),
            'hostport'        => env('DB_PORT', '3306'),
            'params'          => [],
            'charset'         => env('DB_CHARSET', 'utf8mb4'),
            'prefix'          => '',
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => true,
            'trigger_sql'     => env('APP_DEBUG', false),
            'fields_cache'    => false,
        ],
    ],
];
