# -*- coding: utf-8 -*-
"""规则引擎：把"报价 + 日K"变成"该不该提醒"。纯函数式，状态由外部传入。"""
from __future__ import annotations

import json
import os
import time
from dataclasses import dataclass, field
from datetime import datetime
from typing import Dict, List, Optional, Tuple

from market import MarketClient, Quote, ma_pair
from notify import Alert

TRIGGER_ALIAS = {
    "pct_up": "pct_up", "涨幅": "pct_up", "涨": "pct_up",
    "pct_down": "pct_down", "跌幅": "pct_down", "跌": "pct_down",
    "price_above": "price_above", "价格突破": "price_above", "突破": "price_above",
    "price_below": "price_below", "跌破": "price_below",
    "vol_ratio": "vol_ratio", "量比": "vol_ratio",
    "turnover": "turnover", "换手率": "turnover",
    "amplitude": "amplitude", "振幅": "amplitude",
    "ma_cross": "ma_cross", "均线金叉死叉": "ma_cross", "均线交叉": "ma_cross",
    "ma_above": "ma_above", "站上均线": "ma_above",
    "ma_below": "ma_below", "跌破均线": "ma_below",
    "near_high": "near_high", "接近年内高点": "near_high",
    "near_low": "near_low", "接近年内低点": "near_low",
}

DIRECTIONS = {"above", "below", "up", "down", "cross_up", "cross_down"}

DEFAULT_COOLDOWN = 300   # 秒


@dataclass
class Rule:
    id: str
    trigger: str
    symbols: List[str] = field(default_factory=list)
    threshold: float = 0.0
    threshold2: float = 0.0
    direction: str = "above"
    fast: int = 5
    slow: int = 20
    basis: str = "price"          # price | close
    cooldown: int = DEFAULT_COOLDOWN
    note: str = ""
    enabled: bool = True
    raw: Dict = field(default_factory=dict)


def parse_rule(index: int, obj: dict) -> Optional[Rule]:
    trigger_raw = str(obj.get("trigger") or "").strip()
    trigger = TRIGGER_ALIAS.get(trigger_raw)
    if trigger is None:
        # 允许直接写英文规范名
        trigger = trigger_raw if trigger_raw in TRIGGER_ALIAS.values() else None
    if trigger is None:
        return None

    symbols = obj.get("symbols") or []
    if isinstance(symbols, str):
        symbols = [s.strip() for s in symbols.split(",") if s.strip()]

    direction = str(obj.get("direction") or "").strip().lower()
    if not direction:
        direction = {
            "pct_up": "up", "pct_down": "down",
            "price_above": "above", "price_below": "below",
            "vol_ratio": "above", "turnover": "above", "amplitude": "above",
            "ma_cross": "cross_up", "ma_above": "above", "ma_below": "below",
            "near_high": "above", "near_low": "below",
        }[trigger]

    return Rule(
        id=str(obj.get("id") or f"r{index + 1}"),
        trigger=trigger,
        symbols=symbols,
        threshold=float(obj.get("threshold") or 0),
        threshold2=float(obj.get("threshold2") or 0),
        direction=direction,
        fast=int(obj.get("fast") or 5),
        slow=int(obj.get("slow") or 20),
        basis=str(obj.get("basis") or "price"),
        cooldown=int(obj.get("cooldown") or DEFAULT_COOLDOWN),
        note=str(obj.get("note") or ""),
        enabled=bool(obj.get("enabled", True)),
        raw=obj,
    )


class RuleEngine:
    """负责规则求值 + 冷却控制。状态可持久化到 state.json。"""

    def __init__(self, rules: List[dict], watchlist: List[str], state_path: str):
        self.rules: List[Rule] = []
        for i, obj in enumerate(rules):
            r = parse_rule(i, obj)
            if r:
                self.rules.append(r)
            else:
                print(f"  [配置警告] 第 {i + 1} 条规则 trigger 无法识别，已跳过: {obj.get('trigger')}")
        self.watchlist = watchlist
        self.state_path = state_path
        self.state: Dict[str, Dict] = {}
        self._load()

    # ---------- 状态 ----------
    def _load(self) -> None:
        if os.path.exists(self.state_path):
            try:
                with open(self.state_path, "r", encoding="utf-8") as fh:
                    self.state = json.load(fh)
            except (OSError, ValueError):
                self.state = {}

    def save(self) -> None:
        tmp = self.state_path + ".tmp"
        try:
            with open(tmp, "w", encoding="utf-8") as fh:
                json.dump(self.state, fh, ensure_ascii=False, indent=1)
            os.replace(tmp, self.state_path)
        except OSError:
            pass

    def _key(self, rule: Rule, symbol: str) -> str:
        return f"{rule.id}@{symbol}"

    def _fired_recently(self, rule: Rule, symbol: str) -> bool:
        rec = self.state.get(self._key(rule, symbol)) or {}
        last = float(rec.get("last", 0))
        return (time.time() - last) < rule.cooldown

    def _mark_fired(self, rule: Rule, symbol: str) -> None:
        rec = self.state.setdefault(self._key(rule, symbol), {})
        rec["last"] = time.time()
        rec["last_iso"] = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    # ---------- 状态型规则的边沿判定 ----------
    EDGE_TRIGGERS = {"price_above", "price_below", "ma_above", "ma_below"}

    def _edge_ok(self, rule: Rule, symbol: str, hit: bool) -> bool:
        """只在"从未满足 → 满足"的那一刻放行，避免价格停在阈值下方天天报警。
        首次求值只记录状态、不报警 —— 启动时股价早就在 1300 下方，不算"刚跌破"。"""
        if rule.trigger not in self.EDGE_TRIGGERS:
            return hit
        key = self._key(rule, symbol) + ":prev"
        prev = (self.state.get(key) or {}).get("hit")
        self.state[key] = {"hit": hit}
        if prev is None or not hit or prev:
            return False
        return True

    # ---------- 单条规则求值 ----------
    def _check(self, rule: Rule, q: Quote, bars: Optional[List[Dict]]) -> Tuple[bool, str]:
        t = rule.trigger
        name = q.name or q.symbol

        if t == "pct_up":
            hit = q.pct >= rule.threshold
            return hit, f"涨幅 {q.pct:+.2f}% ≥ {rule.threshold:g}%"

        if t == "pct_down":
            hit = q.pct <= -abs(rule.threshold)
            return hit, f"跌幅 {q.pct:+.2f}% ≤ -{abs(rule.threshold):g}%"

        if t == "price_above":
            hit = q.price >= rule.threshold
            return hit, f"现价 {q.price:.3f} 突破 {rule.threshold:g}"

        if t == "price_below":
            hit = q.price <= rule.threshold
            return hit, f"现价 {q.price:.3f} 跌破 {rule.threshold:g}"

        if t == "vol_ratio":
            hit = q.vol_ratio >= rule.threshold
            return hit, f"量比 {q.vol_ratio:.2f} ≥ {rule.threshold:g}"

        if t == "turnover":
            hit = q.turnover >= rule.threshold
            return hit, f"换手率 {q.turnover:.2f}% ≥ {rule.threshold:g}%"

        if t == "amplitude":
            hit = q.amplitude >= rule.threshold
            return hit, f"振幅 {q.amplitude:.2f}% ≥ {rule.threshold:g}%"

        if t in ("ma_cross", "ma_above", "ma_below", "near_high", "near_low"):
            if q.is_index:
                return False, ""
            if q.price <= 0:
                return False, ""

        if t == "ma_cross":
            if not bars:
                return False, ""
            fast, slow, pfast, pslow = ma_pair(bars, rule.fast, rule.slow)
            if None in (fast, slow, pfast, pslow):
                return False, ""
            want_up = rule.direction != "cross_down"
            crossed = (pfast <= pslow and fast > slow) if want_up else (pfast >= pslow and fast < slow)
            if not crossed:
                return False, ""
            tag = "金叉" if want_up else "死叉"
            return True, f"MA{rule.fast}/MA{rule.slow} {tag}（{fast:.2f} / {slow:.2f}）"

        if t in ("ma_above", "ma_below"):
            if not bars:
                return False, ""
            _f_, slow, _pf, _ps = ma_pair(bars, rule.fast, rule.slow)
            if slow is None:
                return False, ""
            if t == "ma_above":
                hit = q.price > slow
                return hit, f"现价 {q.price:.3f} 站上 MA{rule.slow}（{slow:.2f}）"
            hit = q.price < slow
            return hit, f"现价 {q.price:.3f} 跌破 MA{rule.slow}（{slow:.2f}）"

        if t == "near_high":
            if q.high_52w <= 0:
                return False, ""
            gap = (q.high_52w - q.price) / q.high_52w * 100
            hit = gap <= abs(rule.threshold)
            return hit, f"距年内高点 {q.high_52w:.2f} 仅 {gap:.2f}%"

        if t == "near_low":
            if q.low_52w <= 0:
                return False, ""
            gap = (q.price - q.low_52w) / q.low_52w * 100
            hit = gap <= abs(rule.threshold)
            return hit, f"距年内低点 {q.low_52w:.2f} 仅 {gap:.2f}%"

        return False, ""

    # ---------- 批量求值 ----------
    def evaluate(self, quotes: Dict[str, Quote], client: MarketClient) -> List[Alert]:
        alerts: List[Alert] = []
        for rule in self.rules:
            if not rule.enabled:
                continue
            targets = rule.symbols or self.watchlist
            need_bars = rule.trigger in ("ma_cross", "ma_above", "ma_below")

            for sym in targets:
                q = quotes.get(sym)
                if q is None or q.price <= 0:
                    continue
                if self._fired_recently(rule, sym):
                    continue

                bars = None
                if need_bars:
                    try:
                        bars = client.get_daily_bars(sym, count=max(rule.slow + 10, 40))
                    except Exception:
                        bars = None
                    if not bars:
                        continue

                try:
                    hit, detail = self._check(rule, q, bars)
                    if hit:
                        hit = self._edge_ok(rule, sym, hit)
                except Exception:
                    continue
                if not hit:
                    continue

                self._mark_fired(rule, sym)
                msg = detail if not rule.note else f"{detail}（{rule.note}）"
                alerts.append(Alert(
                    symbol=sym, name=q.name, price=q.price, pct=q.pct,
                    rule_id=rule.id, message=msg,
                ))
        return alerts
