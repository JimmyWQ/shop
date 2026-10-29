# -*- coding: utf-8 -*-
"""盯盘助手入口。

用法:
  python monitor.py                 # 常驻循环（默认每 60 秒一轮）
  python monitor.py --once          # 只跑一轮，适合任务计划/手动检查
  python monitor.py --interval 30   # 自定义轮询间隔（秒）
  python monitor.py --let-trade     # 忽略交易时段，休市也跑
  python monitor.py --no-desktop    # 关闭桌面弹窗
  python monitor.py --dry-run       # 只判断不提醒（调试规则）
  python monitor.py --test-notify   # 发一条测试提醒，验证通道是否通
"""
from __future__ import annotations

import argparse
import json
import os
import sys
import time
from datetime import datetime, time as dtime
from typing import List, Optional

from market import MarketClient, Quote, normalize_symbol
from notify import Alert, Notifier
from rules import RuleEngine

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_FILE = os.path.join(BASE_DIR, "config.json")
STATE_FILE = os.path.join(BASE_DIR, "state.json")

# A 股交易时段（不含节假日判断，节假日靠行情是否还在更新来兜底）
MORNING = (dtime(9, 25), dtime(11, 31))
AFTERNOON = (dtime(12, 59), dtime(15, 1))


def log(msg: str) -> None:
    line = f"{datetime.now():%Y-%m-%d %H:%M:%S} {msg}"
    print(line, flush=True)
    try:
        with open(os.path.join(BASE_DIR, "monitor.log"), "a", encoding="utf-8") as fh:
            fh.write(line + "\n")
    except OSError:
        pass


def in_trading_session(now: Optional[datetime] = None) -> bool:
    now = now or datetime.now()
    if now.weekday() >= 5:      # 周六日
        return False
    t = now.time()
    return (MORNING[0] <= t <= MORNING[1]) or (AFTERNOON[0] <= t <= AFTERNOON[1])


def load_config(path: str) -> dict:
    if not os.path.exists(path):
        raise SystemExit(f"找不到配置文件: {path}")
    with open(path, "r", encoding="utf-8-sig") as fh:
        return json.load(fh)


def resolve_symbols(cfg: dict) -> List[str]:
    watch = [normalize_symbol(s) for s in (cfg.get("watchlist") or [])]
    watch = [s for s in watch if s]
    # 规则里单独指定的代码也纳入抓取范围
    rules = cfg.get("rules") or []
    for r in rules:
        extra = r.get("symbols") or []
        if isinstance(extra, str):
            extra = [x.strip() for x in extra.split(",") if x.strip()]
        for s in extra:
            sym = normalize_symbol(s)
            if sym and sym not in watch:
                watch.append(sym)
    return watch


def print_snapshot(quotes: dict, symbols: List[str]) -> None:
    ts = datetime.now().strftime("%H:%M:%S")
    print(f"\n[{ts}] 行情快照（共 {len(symbols)} 只）", flush=True)
    print(f"  {'代码':<10}{'名称':<10}{'现价':>10}{'涨跌幅':>9}{'量比':>7}{'换手%':>8}", flush=True)
    for sym in symbols:
        q = quotes.get(sym)
        if not q:
            print(f"  {sym:<10}{'获取失败':<10}", flush=True)
            continue
        print(f"  {sym:<10}{q.name:<10}{q.price:>10.3f}{q.pct:>8.2f}%"
              f"{q.vol_ratio:>7.2f}{q.turnover:>8.2f}", flush=True)


def run_round(client: MarketClient, engine: RuleEngine, notifier: Notifier,
              symbols: List[str], args, round_no: int = 0) -> int:
    quotes = client.get_quotes(symbols)
    if not quotes:
        log("本轮行情抓取失败，稍后重试")
        return 0

    alerts = engine.evaluate(quotes, client)
    engine.save()

    if not args.once:
        # 常驻模式每轮写一条心跳，便于确认服务在跑、看行情是否过期
        log(f"R{round_no} | 行情 {len(quotes)}/{len(symbols)} 只 | 触发 {len(alerts)} 条")

    if not args.quiet:
        print_snapshot(quotes, symbols)

    if args.dry_run and alerts:
        print(f"\n  [dry-run] 命中 {len(alerts)} 条规则（未发送提醒）:", flush=True)
        for a in alerts:
            print(f"    - {a.rule_id} {a.name} {a.price:.3f} {a.pct:+.2f}%  {a.message}", flush=True)
        return len(alerts)

    if alerts:
        notifier.dispatch(alerts, daemon=not args.once)
    elif not args.quiet:
        print("  无触发", flush=True)
    return len(alerts)


def main(argv: Optional[List[str]] = None) -> int:
    ap = argparse.ArgumentParser(description="股票盯盘助手")
    ap.add_argument("--config", default=CONFIG_FILE)
    ap.add_argument("--once", action="store_true", help="只跑一轮")
    ap.add_argument("--interval", type=int, default=0, help="轮询间隔（秒），0 表示用配置值")
    ap.add_argument("--let-trade", action="store_true", help="忽略交易时段限制")
    ap.add_argument("--no-desktop", action="store_true", help="关闭桌面弹窗")
    ap.add_argument("--dry-run", action="store_true", help="只判断不提醒")
    ap.add_argument("--test-notify", action="store_true", help="发一条测试提醒")
    ap.add_argument("--quiet", action="store_true", help="不打印行情快照")
    args = ap.parse_args(argv)

    cfg = load_config(args.config)
    if args.no_desktop:
        cfg.setdefault("notify", {}).setdefault("desktop", {})["enabled"] = False

    symbols = resolve_symbols(cfg)
    if not symbols:
        raise SystemExit("watchlist 为空，请先在 config.json 里配置自选股")

    interval = args.interval or int((cfg.get("monitor") or {}).get("interval", 60))
    interval = max(5, interval)

    client = MarketClient(
        timeout=float((cfg.get("monitor") or {}).get("timeout", 8)),
        retries=int((cfg.get("monitor") or {}).get("retries", 2)),
    )
    notifier = Notifier(cfg.get("notify") or {}, BASE_DIR)
    engine = RuleEngine(cfg.get("rules") or [], symbols, STATE_FILE)

    mode_label = "测试提醒" if args.test_notify else ("单次" if args.once else "常驻")
    log(f"盯盘启动：{len(symbols)} 只，{len(engine.rules)} 条规则，"
        f"间隔 {interval}s，模式={mode_label}")
    log(f"自选股: {', '.join(symbols)}")
    log(f"规则: {', '.join(r.id + ':' + r.trigger for r in engine.rules)}" if engine.rules
        else "规则: 无（只抓行情）")

    if args.test_notify:
        demo = Alert(symbol="sh600519", name="贵州茅台", price=1326.11, pct=-2.20,
                     rule_id="test", message="这是一条测试提醒，用于验证提醒通道")
        notifier.dispatch([demo], daemon=False)
        log("测试提醒已发送，请检查弹窗/CSV/Webhook")
        return 0

    if args.once:
        n = run_round(client, engine, notifier, symbols, args)
        log(f"单次检查完成，触发 {n} 条")
        return 0

    # ---------- 常驻模式 ----------
    notifier._ensure_root()          # Tk 必须在主线程初始化
    notifier._tk_root.after(300, notifier.pump)
    stop = {"flag": False}

    def monitoring_loop() -> None:
        quiet_logged = False
        last_day = datetime.now().date()
        round_no = 0
        stop_file = os.path.join(BASE_DIR, "STOP")
        while not stop["flag"]:
            if os.path.exists(stop_file):
                log("检测到 STOP 文件，准备退出")
                try:
                    os.remove(stop_file)
                except OSError:
                    pass
                stop["flag"] = True
                notifier._tk_root.after(0, notifier._tk_root.quit)
                break

            now = datetime.now()
            if now.date() != last_day:      # 跨天，清掉冷却记录
                engine.state.clear()
                engine.save()
                last_day = now.date()
                log("跨天，已清空提醒冷却状态")

            if not args.let_trade and not in_trading_session(now):
                if not quiet_logged:
                    log("非交易时段，暂停轮询（用 --let-trade 可强制运行）")
                    quiet_logged = True
                time.sleep(15)
                continue
            quiet_logged = False

            round_no += 1
            try:
                run_round(client, engine, notifier, symbols, args, round_no)
            except Exception as exc:
                log(f"R{round_no} 本轮异常: {exc}")
            for _ in range(interval):
                if stop["flag"] or os.path.exists(stop_file):
                    break
                time.sleep(1)

    import threading
    thread = threading.Thread(target=monitoring_loop, daemon=True)
    thread.start()

    try:
        notifier._tk_root.mainloop()
    except KeyboardInterrupt:
        pass
    finally:
        stop["flag"] = True
        engine.save()
        log("盯盘已退出")
    return 0


if __name__ == "__main__":
    try:
        sys.exit(main())
    except KeyboardInterrupt:
        print("\n已手动中断")
