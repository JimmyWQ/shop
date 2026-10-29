# 股票盯盘助手

后台轮询行情 → 按规则判断 → 多通道提醒，纯 Python 标准库实现，无需安装第三方包。

> 数据来源为腾讯公开行情接口（实时价延迟约几秒到十几秒）。**仅供个人参考，不构成投资建议**，
> 本工具只读取公开行情，不具备任何下单能力。

---

## 1. 环境

- Windows + Python 3.8 以上（本机已验证 3.11.9）
- 无需 `pip install` 任何东西
- 需要能访问 `qt.gtimg.cn` 和 `web.ifzq.gtimg.cn`

---

## 2. 三步跑起来

```powershell
cd D:\Personal\Documents\deepseek-harness\default-workspace\stock-monitor

# ① 先测提醒通道通不通（会弹一个桌面窗口、写一行 CSV）
python monitor.py --test-notify

# ② 试跑一轮，只看不提醒
python monitor.py --once --dry-run

# ③ 正式常驻盯盘
python monitor.py
```

启动后窗口里会打印行情快照；有触发时右上角弹窗 + 蜂鸣 + 写 CSV，同时每轮往
`monitor.log` 记一条心跳（`R1 | 行情 5/5 只 | 触发 0 条`），方便确认它真在跑。

**停止常驻进程**：在目录下新建一个空文件 `STOP`，1 秒内会优雅退出并写日志：

```powershell
New-Item -ItemType File -Name STOP -Force
```

---

## 3. 配置 `config.json`

### 3.1 自选股

```json
"watchlist": ["sh600519", "sz000001", "sz300750", "sh601318", "sz002594"]
```

代码写法：`sh` + 6 位（沪）／`sz` + 6 位（深）／`bj` + 6 位（北交所），也接受
直接写 `600519`、`000001.SZ`，会自动纠正。指数同样支持（如 `sh000001` 上证指数）。

### 3.2 规则

每条规则都是 `{id, trigger, threshold, symbols, ...}`，`symbols` 省略则对全部自选股生效。

| trigger（中文别名） | 含义 | 关键参数 |
|---|---|---|
| `涨幅` / `pct_up` | 涨幅达到阈值 | `threshold` |
| `跌幅` / `pct_down` | 跌幅达到阈值 | `threshold`（写正数） |
| `突破` / `price_above` | 现价上穿某价（边沿触发） | `threshold` |
| `跌破` / `price_below` | 现价下穿某价（边沿触发） | `threshold` |
| `量比` / `vol_ratio` | 量比达到阈值 | `threshold` |
| `换手率` / `turnover` | 换手率达到阈值（%） | `threshold` |
| `振幅` / `amplitude` | 振幅达到阈值（%） | `threshold` |
| `均线交叉` / `ma_cross` | 金叉/死叉 | `fast` `slow` `direction` |
| `站上均线` / `ma_above` | 现价上穿均线（边沿触发） | `fast` `slow` |
| `跌破均线` / `ma_below` | 现价下穿均线（边沿触发） | `fast` `slow` |
| `接近年内高点` / `near_high` | 距 52 周高点 N% 内 | `threshold` |
| `接近年内低点` / `near_low` | 距 52 周低点 N% 内 | `threshold` |

两条关键设计，避免被消息淹没：

- **边沿触发**：`突破`/`跌破`/均线穿越只在"刚刚穿过"那一刻响一次。启动时股价早就
  在 1300 下方不会立刻报警，要等它真的从上方穿下来。
- **冷却时间**：`cooldown`（秒）内同一规则+同一只股票不重复提醒。默认 300 秒，
  日线级别信号建议 86400（一天一次）。

### 3.3 提醒通道

`notify.desktop` 控制弹窗与落盘：

```json
"desktop": {
  "enabled": true,        // 桌面弹窗开关
  "corner": "top-right",  // top-right / top-left / bottom-right / bottom-left
  "duration_ms": 6000,    // 弹窗停留毫秒，点一下可立即关闭
  "sound": true,          // 蜂鸣
  "write_csv": true,      // 落盘 alerts.csv（UTF-8 BOM，Excel 直接打开不乱码）
  "csv_file": "alerts.csv",
  "log_file": "monitor.log"
}
```

`notify.webhook` 推到手机（Server酱、钉钉、飞书、企业微信、Bark 都行）。开启后每次
触发都会 POST 一个 JSON，默认体是 `{"text": "...", "title": "股票提醒", "alerts": [[...]]}`。
如果对方要求特定格式，用 `body_template` 自定义，其中 `{text}` 会替换成多行提醒文本：

```json
"webhook": {
  "enabled": true,
  "url": "https://sctapi.ftqq.com/你的SENDKEY.send",
  "method": "POST",
  "headers": { "Content-Type": "application/json; charset=utf-8" },
  "body_template": "{\"title\": \"股票提醒\", \"desp\": \"{text}\"}"
}
```

> 注意：`body_template` 里如果放 JSON，引号要转义。钉钉机器人还需要
> `"headers": {"Content-Type": "application/json", "X-Custom": ""}` 之类的对应格式，
> 建议先在浏览器/Postman 里把目标接口调通，再粘进配置。

---

## 4. 调参：先回放，别上来就盯

规则阈值拍脑袋定，很容易"天天响"或"永远不响"。用历史日线回放一下：

```powershell
python backtest.py --days 120              # 回放最近 120 个交易日
python backtest.py --rules 均线金叉,大跌3  # 只看某几条规则
```

输出示例：

```
规则                命中次数      命中率  最近 4 次
大跌3                  4    0.67%  2026-05-08 sz300750 | 2026-06-18 sh601318 ...
茅台破1300              1    0.17%  2026-05-18 sh600519
```

- 命中率 **>10%**：阈值偏松，实盘会天天响，调大阈值或加长冷却。
- 命中率 **接近 0**：阈值太苛刻，可能永远等不到，考虑放宽。
- 回放已计入冷却与边沿逻辑，所以命中次数≈实盘会响的次数；但盘中急涨急跌这类
  信号无法用日线还原，仍需实盘观察。

---

## 5. 开机自启（可选）

`start-monitor.bat` 已备好，双击即可在后台启动（窗口最小化，日志在 `monitor.log`）。
放进启动文件夹即可开机自启：

1. `Win + R` 输入 `shell:startup` 回车
2. 把 `start-monitor.bat` 的快捷方式拖进去

只想在交易时段跑，脚本本身已内置判断：非交易时段（含周末）自动暂停轮询，
每 15 秒检查一次，开盘自动恢复。要强制全天运行加 `--let-trade`。

注意：多开会造成重复提醒，**同一个目录只跑一个实例**。

---

## 6. 文件说明

| 文件 | 作用 |
|---|---|
| `monitor.py` | 入口：轮询循环 / 单次检查 / 测试提醒 |
| `market.py` | 行情抓取与解析（腾讯接口），带重试和 K 线缓存 |
| `rules.py` | 规则引擎：条件求值、边沿触发、冷却控制 |
| `notify.py` | 提醒通道：控制台 / CSV / 日志 / 桌面弹窗 / 蜂鸣 / Webhook |
| `backtest.py` | 历史回放调参 |
| `config.json` | 你的配置（自选股、规则、通道） |
| `state.json` | 运行时状态（上次触发时间、边沿状态），跨天自动清空 |
| `alerts.csv` | 提醒流水，事后复盘用 |
| `monitor.log` | 运行日志，含每轮心跳 |

---

## 7. 已知限制

1. **不是实时逐笔**：本质是轮询，最快 5 秒一轮，实际行情本身也有几秒延迟。
   要毫秒级需要券商官方 API（QMT / Ptrade）或付费行情源。
2. **无法读取你交易软件的界面/持仓**：本工具只读公开行情，不接入任何券商账户。
3. **节假日不判断**：非交易时段靠时间窗过滤，法定节假日会空转几轮（行情不更新，
   不会误报）。加了 `--let-trade` 时需自行留意。
4. **接口是公开的第三方接口**：不保证长期稳定，若哪天抓不到数据会写明原因并重试，
   长期失效需要换数据源（结构已抽象在 `market.py`，替换成本低）。
5. 桌面弹窗依赖 Windows 图形会话；以服务/计划任务方式在无桌面会话下运行时会
   自动降级为只写 CSV/日志/Webhook，不会报错。
