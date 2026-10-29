<?php

namespace app\command;

use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;
use think\facade\Db;

class MallInstall extends Command
{
    protected function configure(): void
    {
        $this->setName('mall:install')
            ->setDescription('创建 NOVA 商城数据表并写入演示数据')
            ->addOption('force', 'f', Option::VALUE_NONE, '删除现有商城数据并重新安装');
    }

    protected function execute(Input $input, Output $output): int
    {
        $driver = (string) config('database.default');
        $force = (bool) $input->getOption('force');

        try {
            if ($driver === 'sqlite') {
                $database = (string) config('database.connections.sqlite.database');
                if (!preg_match('#^([A-Za-z]:[\\\\/]|/)#', $database)) {
                    $database = root_path() . ltrim($database, '/\\');
                }
                $dir = dirname($database);
                if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
                    $output->error('无法创建数据库目录：' . $dir);
                    return 1;
                }
                if (!extension_loaded('pdo_sqlite')) {
                    $output->error('PHP 未启用 pdo_sqlite 扩展。请启用后重试，或在 .env 中配置 MySQL。');
                    return 1;
                }
                $schemaFile = root_path() . 'database/schema.sqlite.sql';
            } else {
                $database = (string) config('database.connections.mysql.database');
                $schemaFile = root_path() . 'database/schema.mysql.sql';
            }

            if (!$force && $this->tableExists('users')) {
                $output->warning('商城数据表已经存在。如需清空并重建，请运行：php think mall:install --force');
                return 1;
            }

            $sql = file_get_contents($schemaFile);
            if ($sql === false) {
                $output->error('无法读取数据结构文件。');
                return 1;
            }

            foreach ($this->splitSql($sql) as $statement) {
                Db::execute($statement);
            }

            $this->seed($output);
        } catch (\Throwable $e) {
            $output->error('安装失败：' . $e->getMessage());
            return 1;
        }

        $output->writeln('');
        $output->info('NOVA 商城安装完成。');
        $output->writeln('  前台：http://127.0.0.1:8000');
        $output->writeln('  后台：http://127.0.0.1:8000/admin/login');
        $output->writeln('  管理员：admin@nova.test / Admin@123');
        $output->writeln('  演示用户：demo@nova.test / Demo@123');
        $output->writeln('  数据驱动：' . $driver . ' / ' . $database);
        $output->writeln('');
        $output->writeln('启动：php -d extension=pdo_sqlite think run -H 127.0.0.1 -p 8000');
        return 0;
    }

    private function tableExists(string $table): bool
    {
        try {
            if (config('database.default') === 'sqlite') {
                return (bool) Db::query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?", [$table]);
            }
            return !empty(Db::query('SHOW TABLES LIKE ?', [$table]));
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function splitSql(string $sql): array
    {
        $parts = preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [];
        return array_values(array_filter(array_map('trim', $parts), fn ($part) => $part !== ''));
    }

    private function seed(Output $output): void
    {
        $data = require root_path() . 'database/seed.php';
        $now = date('Y-m-d H:i:s');

        $userIds = [];
        foreach ($data['users'] as $user) {
            $userIds[$user['role']] = Db::name('users')->insertGetId([
                'name'        => $user['name'],
                'email'       => $user['email'],
                'password'    => password_hash($user['password'], PASSWORD_DEFAULT),
                'phone'       => $user['phone'],
                'role'        => $user['role'],
                'status'      => 1,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        $categoryIds = [];
        foreach ($data['categories'] as $category) {
            $categoryIds[$category['slug']] = Db::name('categories')->insertGetId([
                'name'        => $category['name'],
                'slug'        => $category['slug'],
                'description' => $category['description'],
                'cover'       => $category['cover'],
                'sort'        => $category['sort'],
                'status'      => 1,
                'create_time' => $now,
                'update_time' => $now,
            ]);
        }

        $products = [];
        foreach ($data['products'] as $product) {
            $id = Db::name('products')->insertGetId([
                'category_id'    => $categoryIds[$product['category_slug']],
                'name'           => $product['name'],
                'slug'           => $product['slug'],
                'subtitle'       => $product['subtitle'],
                'description'    => $product['description'],
                'highlights'     => $product['highlights'],
                'specs'          => $product['specs'],
                'price'          => $product['price'],
                'original_price' => $product['original_price'],
                'stock'          => $product['stock'],
                'sales'          => $product['sales'],
                'cover'          => $product['cover'],
                'badge'          => $product['badge'],
                'featured'       => $product['featured'],
                'status'         => $product['status'],
                'sort'           => $product['sort'],
                'create_time'    => $now,
                'update_time'    => $now,
            ]);
            $products[$product['slug']] = array_merge($product, ['id' => $id]);
        }

        $customers = Db::name('users')->where('role', 'customer')->order('id')->select();
        $customerA = $customers[0] ?? null;
        $customerB = $customers[1] ?? null;

        if ($customerA) {
            $this->seedOrder($customerA, [
                ['slug' => 'orbit-speaker', 'qty' => 1],
                ['slug' => 'mono-glass', 'qty' => 2],
            ], $products, 'completed', 6, 'wechat', '浙江省 杭州市 西湖区 文三路 88 号 2 幢 501', '周末在家听歌，慢慢拆。');
            $this->seedOrder($customerA, [
                ['slug' => 'luma-lamp', 'qty' => 1],
                ['slug' => 'bloom-candle', 'qty' => 1],
            ], $products, 'shipped', 2, 'alipay', '浙江省 杭州市 西湖区 文三路 88 号 2 幢 501', '');
            $this->seedOrder($customerA, [
                ['slug' => 'aero-headphones', 'qty' => 1],
            ], $products, 'pending', 0.1, 'wechat', '浙江省 杭州市 西湖区 文三路 88 号 2 幢 501', '希望发顺丰，谢谢。');
        }

        if ($customerB) {
            $this->seedOrder($customerB, [
                ['slug' => 'trail-backpack', 'qty' => 1],
                ['slug' => 'dew-serum', 'qty' => 1],
            ], $products, 'paid', 1, 'alipay', '上海市 静安区 愚园路 120 号 8 楼', '');
            $this->seedOrder($customerB, [
                ['slug' => 'frame-watch', 'qty' => 1],
            ], $products, 'completed', 12, 'wechat', '上海市 静安区 愚园路 120 号 8 楼', '送人的，帮忙包好看一点。');
        }

        $output->writeln('已写入 ' . count($data['products']) . ' 件商品、' . count($data['categories']) . ' 个分类和 5 笔演示订单。');
    }

    private function seedOrder(array $user, array $items, array $products, string $status, float $daysAgo, string $payment, string $address, string $remark): void
    {
        $createdTs = time() - (int) ($daysAgo * 86400);
        $created = date('Y-m-d H:i:s', $createdTs);
        $lines = [];
        $subtotal = 0.0;
        $quantity = 0;

        foreach ($items as $item) {
            $product = $products[$item['slug']] ?? null;
            if (!$product) {
                continue;
            }
            $qty = (int) $item['qty'];
            $lineTotal = round((float) $product['price'] * $qty, 2);
            $subtotal += $lineTotal;
            $quantity += $qty;
            $lines[] = [
                'product_id'    => $product['id'],
                'product_name'  => $product['name'],
                'product_cover' => $product['cover'],
                'price'         => $product['price'],
                'quantity'      => $qty,
                'subtotal'      => $lineTotal,
                'create_time'   => $created,
            ];
        }

        $subtotal = round($subtotal, 2);
        $shipping = $subtotal < 299 ? 15.0 : 0.0;
        $payTime = in_array($status, ['paid', 'shipped', 'completed'], true) ? date('Y-m-d H:i:s', $createdTs + 300) : null;
        $shipTime = in_array($status, ['shipped', 'completed'], true) ? date('Y-m-d H:i:s', $createdTs + 86400) : null;
        $completeTime = $status === 'completed' ? date('Y-m-d H:i:s', $createdTs + 4 * 86400) : null;

        $orderId = Db::name('orders')->insertGetId([
            'order_no'       => date('ymdHis', $createdTs) . '9' . str_pad((string) $user['id'], 3, '0', STR_PAD_LEFT),
            'user_id'        => $user['id'],
            'status'         => $status,
            'payment_method' => $payment,
            'subtotal'       => $subtotal,
            'shipping_fee'   => $shipping,
            'total_amount'   => round($subtotal + $shipping, 2),
            'item_count'     => $quantity,
            'receiver'       => $user['name'],
            'phone'          => $user['phone'],
            'address'        => $address,
            'remark'         => $remark,
            'pay_time'       => $payTime,
            'ship_time'      => $shipTime,
            'complete_time'  => $completeTime,
            'create_time'    => $created,
            'update_time'    => $completeTime ?? $shipTime ?? $payTime ?? $created,
        ]);

        foreach ($lines as &$line) {
            $line['order_id'] = $orderId;
        }
        unset($line);
        Db::name('order_items')->insertAll($lines);
    }
}
