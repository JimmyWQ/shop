# NOVA 商城

一个基于 **ThinkPHP 8** 的全栈商城网站。前后台均为服务端渲染，零前端构建依赖，开箱即可运行。

NOVA 的定位是“认真挑选日常好物”的精品选物店：前台包含商品浏览、搜索、购物车、结算、模拟支付、订单与个人中心；后台包含经营看板、商品 / 分类 / 订单 / 用户管理。

---

## 技术栈

| 层 | 技术 |
| --- | --- |
| 框架 | ThinkPHP 8.1（PHP >= 8.0） |
| 模板 | ThinkPHP Template（think-view） |
| 数据库 | SQLite（默认，零配置）/ MySQL 5.7+ |
| 前端 | 原生 CSS + 原生 JavaScript，响应式布局 |
| 图片 | 本地 SVG 商品插画，无外链依赖 |
| 安全 | Session 认证、CSRF、密码哈希、ORM 参数绑定、订单归属校验 |

---

## 功能一览

### 前台

- 首页：品牌 Hero、分类导航、本周精选、新到好物、品牌故事
- 商品：分类浏览、关键词搜索、价格 / 热销排序、分页
- 商品详情：卖点、规格参数、库存、数量选择、加入购物车 / 立即购买
- 购物车：数量修改、移除、清空、免运费进度、实时小计
- 结算：收货信息校验、微信 / 支付宝 / 货到付款（演示）、订单摘要
- 订单：创建、模拟支付、取消（库存回补）、确认收货、状态时间线
- 账户：注册 / 登录 / 登出、资料修改、密码修改、订单列表与筛选
- 内容页：关于 NOVA、配送与退换、联系我们、品牌 404 页面

### 后台（`/admin`）

- 经营看板：销售额、今日销售、订单数、客户数、近 7 天销售图、热销商品、库存预警
- 商品管理：搜索 / 筛选、新建、编辑、上下架、删除、封面图上传
- 分类管理：新建、编辑、启停、删除（有商品时保护）
- 订单管理：状态筛选、订单搜索、确认收款、发货、完成、取消并回补库存
- 用户管理：搜索、角色 / 状态筛选、启用 / 停用（管理员账号受保护）

### 工程细节

- 订单创建使用数据库事务；库存通过条件更新防止超卖
- 订单明细保存商品名称、封面、价格快照，商品后续修改 / 删除不影响历史订单
- 全站写请求 CSRF 校验；AJAX 请求返回 JSON，普通表单失败后回填旧值
- 登录成功后轮换 Session ID；密码使用 `password_hash`
- 模板默认输出转义；上传仅允许 JPG / PNG / WebP，限制 2MB

---

## 快速开始（Windows，推荐）

### 环境要求

- PHP **8.0+**（需启用 `pdo_sqlite`，或改用 MySQL 的 `pdo_mysql`）
- Composer 2.x
- 可选：Node.js 不需要，本项目没有前端构建步骤

### 一键启动

```powershell
composer install
Copy-Item .example.env .env
.\start.ps1
```

打开 <http://127.0.0.1:8000>。

`start.ps1` 会自动：

1. 检测 `pdo_sqlite`，未启用时通过 `-d extension=pdo_sqlite` 加载；
2. 首次运行时执行 `mall:install` 创建 SQLite 数据库并写入演示数据；
3. 启动 PHP 内置服务器。

也可以双击 `start.bat`。

### 手动启动

```powershell
# 安装依赖
composer install

# 初始化数据库（会重建表并写入演示数据）
php -d extension=pdo_sqlite think mall:install --force

# 启动服务器
php -d extension=pdo_sqlite -S 127.0.0.1:8000 -t public public/router.php
```

> 如果你的 PHP 已经启用 `pdo_sqlite`，去掉 `-d extension=pdo_sqlite` 即可。
>
> 如果使用 `php think run`，请确保启动的 PHP 进程同样启用了 `pdo_sqlite`，否则会提示无法打开数据库。

---

## 演示账号

| 身份 | 邮箱 | 密码 |
| --- | --- | --- |
| 管理员 | `admin@nova.test` | `Admin@123` |
| 顾客 | `demo@nova.test` | `Demo@123` |
| 顾客 | `chen@nova.test` | `Demo@123` |

后台入口：<http://127.0.0.1:8000/admin/login>

演示数据包含 6 个分类、12 件商品、3 位用户和 5 笔不同状态的订单，方便直接查看看板与订单流程。

> 上线前请务必修改演示账号密码，并删除或重置演示数据。

---

## 使用 MySQL

默认使用 SQLite，适合本地演示和快速体验。若要使用 MySQL：

1. 创建数据库，例如 `nova_mall`（字符集 `utf8mb4`）。
2. 编辑 `.env`：

```dotenv
APP_DEBUG = true
DB_DRIVER = mysql
DB_HOST = 127.0.0.1
DB_PORT = 3306
DB_NAME = nova_mall
DB_USER = root
DB_PASS = your_password
DB_CHARSET = utf8mb4
```

3. 初始化表结构与演示数据：

```powershell
php think mall:install --force
```

4. 启动服务（`pdo_mysql` 已启用时）：

```powershell
php -S 127.0.0.1:8000 -t public public/router.php
```

MySQL 表结构位于 `database/schema.mysql.sql`，SQLite 表结构位于 `database/schema.sqlite.sql`。

---

## 目录结构

```text
app/
  command/MallInstall.php      # 安装命令：建表 + 演示数据
  controller/                  # 前台控制器
    admin/                     # 后台控制器
  middleware/                  # Session / CSRF / 登录 / 管理员校验
  model/                       # User / Category / Product / Order / OrderItem
  service/                     # 购物车、订单领域逻辑
  BaseController.php
  common.php                   # 模板辅助函数
config/                        # 应用、数据库、路由、Session 配置
database/
  schema.sqlite.sql
  schema.mysql.sql
  seed.php
  mall.sqlite                  # 运行后生成
public/
  index.php
  router.php
  static/css/app.css
  static/css/admin.css
  static/js/app.js
  static/images/products/*.svg
  uploads/                     # 后台上传目录
route/app.php                  # 全部路由
view/
  layout/store.html            # 前台布局
  layout/admin.html            # 后台布局
  index/ product/ cart/ ...    # 前台模板
  admin/                       # 后台模板
start.ps1 / start.bat
```

---

## 关键流程

### 下单

1. 购物车读取 Session 中的商品与数量；
2. 结算页校验收货信息与支付方式；
3. `OrderService::create()` 开启事务，逐件校验库存并用条件更新扣减；
4. 写入订单主表与订单明细快照；
5. 清空购物车，跳转订单详情。

### 取消订单

仅 `pending`（待付款）与 `paid`（待发货）可取消。取消时在同一事务内更新订单状态并回补库存；`shipped` / `completed` 不允许取消。

### 登录后回跳

需要登录的页面会记录目标地址，登录页通过 `next` 参数回跳，且只允许站内相对路径，避免开放重定向。

---

## 主要路由

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| GET | `/` | 首页 |
| GET | `/shop` | 全部商品 / 搜索 |
| GET | `/category/:slug` | 分类商品 |
| GET | `/product/:slug` | 商品详情 |
| GET/POST | `/cart`、`/cart/add`、`/cart/update` | 购物车 |
| GET/POST | `/checkout` | 结算 |
| GET/POST | `/login`、`/register` | 登录注册 |
| GET | `/account`、`/account/orders` | 个人中心 |
| GET | `/order/:order_no` | 订单详情 |
| POST | `/order/:order_no/pay` | 模拟支付 |
| GET | `/admin` | 后台看板 |
| GET/POST | `/admin/products` ... | 商品管理 |
| GET/POST | `/admin/orders` ... | 订单管理 |

完整列表：`php think route:list`。

---

## 开发与维护

```powershell
# 清空并重建演示数据
php -d extension=pdo_sqlite think mall:install --force

# 路由列表
php -d extension=pdo_sqlite think route:list

# 清缓存
php -d extension=pdo_sqlite think clear
```

修改模板后直接刷新即可（已关闭模板缓存，见 `config/view.php`）。生产环境建议开启模板缓存并关闭 `APP_DEBUG`。

---

## 生产部署检查清单

- [ ] `.env` 设置 `APP_DEBUG = false`
- [ ] 修改所有演示账号密码，删除测试用户
- [ ] 使用 MySQL 并配置独立数据库账号（最小权限）
- [ ] Web 根目录指向 `public/`，不要暴露项目根目录
- [ ] 配置 HTTPS，并将 Session Cookie 的 `secure` 设为 `true`
- [ ] 接入真实支付网关，替换演示支付流程
- [ ] 配置对象存储 / CDN 承载商品图，限制上传体积与类型
- [ ] 增加登录与下单频率限制、验证码、审计日志
- [ ] 配置数据库备份、错误日志与监控
- [ ] 在 Nginx 中配置重写规则，将请求转发到 `public/index.php`

Nginx 参考：

```nginx
server {
    listen 80;
    server_name nova.example.com;
    root /path/to/nova/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass 127.0.0.1:9000;
    }
}
```

---

## 说明

- 在线支付是**演示流程**：点击“模拟支付”只会把订单状态更新为已支付，不会产生真实扣款。
- 演示环境未接入短信、物流轨迹、退款网关、优惠券与多规格 SKU；这些可以在现有订单 / 商品模型上继续扩展。
- 商品插画为项目内置 SVG 文件，可自由替换为真实商品图。

项目基于 ThinkPHP 8 构建，遵循原框架的 Apache-2.0 许可。
