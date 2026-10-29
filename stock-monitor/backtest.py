# -*- coding: utf-8 -*-
"""规则回放/调参工具：用历史日K检验规则会不会乱响。

用法:
  python backtest.py                 # 回放最近 120 个交易日
  python backtest.py --days 60       # 只回放最近 60 天
  python backtest.py --rules 均线金叉,大跌3   # 只看指定规则
  python backtest.py --symbols sh600519,sz000001

说明: 回放按"日线收盘价"逐日推进，量比/换手率用当日成交量估算，
      并复用了实盘的冷却与边沿触发逻辑，因此命中次数≈实盘会响的次数。
      盘中急涨急跌这类信号无法用日线回放。
"""
from __future__ import annotations

import argparse
import json
import os
import sys
from collections import defaultdict
from typing import Dict, List, Optional

from market import MarketClient, Quote, normalize_symbol
from rules import RuleEngine

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_FILE = os.path.join(BASE_DIR, "config.json")


def load_config(path: str) -> dict:
    with open(path, "r", encoding="utf-8-sig") as fh:
        return json.load(fh)


def build_quote(symbol: str, bars: List[Dict], idx: int) -> Optional[Quote]:
    """用第 idx 根日K构造一个近似 Quote。"""
    if idx < 1 or idx >= len(bars):
        return None
    bar = bars[idx]
    prev = bars[idx - 1]
    close = bar["close"]
    if close <= 0 or prev["close"] <= 0:
        return None

    vols = [b["volume"] for b in bars[:idx + 1]]
    avg5 = sum(vols[-6:-1]) / 5 if len(vols) >= 6 else 0
    window = bars[max(0, idx - 249):idx + 1]

    return Quote(
        symbol=symbol,
        name=symbol,
        price=close,
        prev_close=prev["close"],
        open=bar["open"],
        high=bar["high"],
        low=bar["low"],
        volume=bar["volume"],
        pct=(close - prev["close"]) / prev["close"] * 100,
        change=close - prev["close"],
        vol_ratio=(bar["volume"] / avg5) if avg5 > 0 else 0.0,
        amplitude=(bar["high"] - bar["low"]) / prev["close"] * 100,
        high_52w=max(b["high"] for b in window),
        low_52w=min(b["low"] for b in window),
        quote_time=bar["date"],
        ts=bar["date"],
    )


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="规则历史回放")
    ap.add_argument("--config", default=CONFIG_FILE)
    ap.add_argument("--days", type=int, default=120, help="回放最近 N 个交易日")
    ap.add_argument("--symbols", default="", help="逗号分隔，默认用 watchlist")
    ap.add_argument("--rules", default="", help="逗号分隔的规则 id 过滤")
    args = ap.parse_args(argv)

    cfg = load_config(args.config)
    symbols = ([normalize_symbol(s) for s in args.symbols.split(",") if s.strip()]
               or [normalize_symbol(s) for s in (cfg.get("watchlist") or [])])
    rule_ids = [r.strip() for r in args.rules.split(",") if r.strip()]

    engine = RuleEngine(cfg.get("rules") or [], symbols, os.devnull)
    rules = [r for r in engine.rules if r.enabled and (not rule_ids or r.id in rule_ids)]
    if not rules:
        print("没有可回放的规则")
        return 1

    client = MarketClient()
    need = args.days + 260     # 多取一年算 52 周高低
    print(f"回放 {len(symbols)} 只、{len(rules)} 条规则、最近 {args.days} 个交易日…\n")

    # 先抓齐数据
    data: Dict[str, List[Dict]] = {}
    for sym in symbols:
        try:
            bars = client.get_daily_bars(sym, count=need)
        except Exception as exc:
            print(f"  {sym} K线获取失败: {exc}")
            continue
        if len(bars) < 30:
            print(f"  {sym} 数据不足，跳过")
            continue
        data[sym] = bars

    if not data:
        print("没有可用数据")
        return 1

    # 按日期对齐，逐日推进（这样冷却与边沿状态才符合实盘）
    dates = sorted({b["date"] for bars in data.values() for b in bars})
    dates = dates[-args.days:]

    hits: Dict[str, List[str]] = defaultdict(list)
    hit_days = 0

    for day in dates:
        for sym, bars in data.items():
            idx = next((i for i, b in enumerate(bars) if b["date"] == day), None)
            if idx is None or idx < 25:
                continue
            q = build_quote(sym, bars, idx)
            if q is None:
                continue
            sub_bars = bars[:idx + 1]
            for rule in rules:
                if rule.symbols and sym not in rule.symbols:
                    continue
                if engine._fired_recently(rule, sym):
                    continue
                try:
                    ok, _ = engine._check(rule, q, sub_bars)
                    if ok:
                        ok = engine._edge_ok(rule, sym, ok)
                except Exception:
                    continue
                if ok:
                    engine._mark_fired(rule, sym)
                    hits[rule.id].append(f"{day} {sym}")

    total_checks = len(dates) * len(data)
    print(f"{'规则':<14}{'命中次数':>8}{'命中率':>9}  最近 4 次")
    print("-" * 88)
    for rule in rules:
        got = hits.get(rule.id, [])
        rate = len(got) / total_checks * 100 if total_checks else 0
        recent = " | ".join(got[-4:]) if got else "-"
        print(f"{rule.id:<14}{len(got):>8}{rate:>8.2f}%  {recent}")

    print(f"\n回放区间: {dates[0]} ~ {dates[-1]}（{len(dates)} 个交易日，"
          f"{len(data)} 只股票，已计入冷却/边沿逻辑）")
    print("解读: 命中率 >10% 说明阈值偏松，可能天天响；接近 0 说明可能永远不触发。")
    print("      盘中急涨急跌类信号无法用日线回放，需实盘观察。")
    return 0


if __name__ == "__main__":
    sys.exit(main())
