# -*- coding: utf-8 -*-
"""行情数据获取层：腾讯免费行情接口，零第三方依赖。

- 实时报价: http://qt.gtimg.cn/q=sh600519,sz000001
- 日K线  : https://web.ifzq.gtimg.cn/appstock/app/fqkline/get?param=sh600519,day,,,60,qfq
"""
from __future__ import annotations

import json
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass, field
from datetime import datetime
from typing import Dict, List, Optional

QUOTE_URL = "http://qt.gtimg.cn/q={symbols}"
KLINE_URL = "https://web.ifzq.gtimg.cn/appstock/app/fqkline/get?param={sym},day,,,{count},{fq}"

HEADERS = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
                  "(KHTML, like Gecko) Chrome/120.0 Safari/537.36",
    "Referer": "https://gu.qq.com/",
}


@dataclass
class Quote:
    symbol: str
    name: str = ""
    price: float = 0.0
    prev_close: float = 0.0
    open: float = 0.0
    high: float = 0.0
    low: float = 0.0
    volume: float = 0.0      # 手
    amount: float = 0.0      # 元
    pct: float = 0.0         # 涨跌幅 %
    change: float = 0.0
    turnover: float = 0.0    # 换手率 %
    vol_ratio: float = 0.0   # 量比
    amplitude: float = 0.0   # 振幅 %
    pe: float = 0.0
    float_cap: float = 0.0   # 流通市值(亿)
    total_cap: float = 0.0   # 总市值(亿)
    high_52w: float = 0.0
    low_52w: float = 0.0
    limit_up: float = 0.0
    limit_down: float = 0.0
    ts: str = ""             # 原始 YYYYMMDDHHMMSS
    quote_time: str = ""     # HH:MM:SS
    extra: Dict[str, str] = field(default_factory=dict)

    @property
    def is_index(self) -> bool:
        # 指数代码：sh000xxx / sz399xxx / 北证指数等
        return self.symbol.startswith(("sh000", "sz399", "bj899"))

    def to_dict(self) -> Dict:
        return {k: v for k, v in self.__dict__.items() if k != "extra"}


def _f(value: str, default: float = 0.0) -> float:
    try:
        if value is None or value == "" or value == "-":
            return default
        return float(value)
    except (TypeError, ValueError):
        return default


def _field(fields: List[str], idx: int, default: str = "") -> str:
    return fields[idx] if idx < len(fields) else default


def parse_quote_line(symbol: str, line: str) -> Optional[Quote]:
    """解析一条 `v_sh600519="1~贵州茅台~600519~...";`"""
    if "=" not in line:
        return None
    body = line.split("=", 1)[1].strip().rstrip(";").strip().strip('"')
    if not body:
        return None
    f = body.split("~")
    if len(f) < 40:
        return None

    ts = _field(f, 30)
    quote_time = ""
    if len(ts) == 14 and ts.isdigit():
        quote_time = f"{ts[8:10]}:{ts[10:12]}:{ts[12:14]}"

    return Quote(
        symbol=symbol,
        name=_field(f, 1),
        price=_f(_field(f, 3)),
        prev_close=_f(_field(f, 4)),
        open=_f(_field(f, 5)),
        volume=_f(_field(f, 6)),
        amount=_f(_field(f, 37)) * 10000.0,  # 字段37单位为万元
        high=_f(_field(f, 33)),
        low=_f(_field(f, 34)),
        pct=_f(_field(f, 32)),
        change=_f(_field(f, 31)),
        turnover=_f(_field(f, 38)),
        vol_ratio=_f(_field(f, 49)),
        amplitude=_f(_field(f, 43)),
        pe=_f(_field(f, 39)),
        float_cap=_f(_field(f, 44)),
        total_cap=_f(_field(f, 45)),
        high_52w=_f(_field(f, 47)),
        low_52w=_f(_field(f, 48)),
        limit_up=_f(_field(f, 47)),
        limit_down=_f(_field(f, 48)),
        ts=ts,
        quote_time=quote_time,
        extra={"raw": body},
    )


class MarketClient:
    """带缓存、重试、节流的行情客户端。"""

    def __init__(self, timeout: float = 8.0, retries: int = 2):
        self.timeout = timeout
        self.retries = retries
        self._kline_cache: Dict[str, tuple] = {}   # symbol -> (expire_ts, bars)
        self.kline_ttl = 600                        # 日K缓存10分钟

    # ---------- 底层 HTTP ----------
    def _get(self, url: str) -> bytes:
        last_err: Optional[Exception] = None
        for attempt in range(self.retries + 1):
            try:
                req = urllib.request.Request(url, headers=HEADERS)
                with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                    return resp.read()
            except (urllib.error.URLError, TimeoutError, OSError) as exc:
                last_err = exc
                if attempt < self.retries:
                    time.sleep(0.6 * (attempt + 1))
        raise RuntimeError(f"请求失败 {url}: {last_err}")

    # ---------- 实时报价 ----------
    def get_quotes(self, symbols: List[str]) -> Dict[str, Quote]:
        """批量抓取，自动分批（每批最多 50 个）。"""
        result: Dict[str, Quote] = {}
        for i in range(0, len(symbols), 50):
            batch = symbols[i:i + 50]
            url = QUOTE_URL.format(symbols=",".join(batch))
            raw = self._get(url)
            try:
                text = raw.decode("gbk")
            except UnicodeDecodeError:
                text = raw.decode("gbk", errors="replace")
            for chunk in text.split(";"):
                chunk = chunk.strip()
                if not chunk:
                    continue
                # 从 v_xxx="..." 中反推 symbol
                try:
                    key = chunk.split("=", 1)[0].strip()
                    sym = key[2:] if key.startswith("v_") else key
                except IndexError:
                    continue
                q = parse_quote_line(sym, chunk)
                if q is not None:
                    result[sym] = q
        return result

    # ---------- 日K线 ----------
    def get_daily_bars(self, symbol: str, count: int = 60, fq: str = "qfq") -> List[Dict]:
        now = time.time()
        cached = self._kline_cache.get(symbol)
        if cached and cached[0] > now:
            return cached[1]

        url = KLINE_URL.format(sym=symbol, count=max(30, count), fq=fq)
        raw = self._get(url).decode("utf-8", errors="replace")
        payload = json.loads(raw)
        node = (payload.get("data") or {}).get(symbol) or {}
        rows = node.get("qfqday") or node.get("day") or []

        bars: List[Dict] = []
        for row in rows:
            if len(row) < 6:
                continue
            try:
                bars.append({
                    "date": str(row[0]),
                    "open": float(row[1]),
                    "close": float(row[2]),
                    "high": float(row[3]),
                    "low": float(row[4]),
                    "volume": float(row[5]),
                })
            except (TypeError, ValueError):
                continue
        self._kline_cache[symbol] = (now + self.kline_ttl, bars)
        return bars


def moving_average(values: List[float], window: int) -> Optional[float]:
    if len(values) < window or window <= 0:
        return None
    return sum(values[-window:]) / window


def ma_pair(bars: List[Dict], fast: int, slow: int) -> tuple:
    """返回 (今日 fast_ma, 今日 slow_ma, 昨日 fast_ma, 昨日 slow_ma)。"""
    closes = [b["close"] for b in bars]
    if len(closes) < slow + 1:
        return None, None, None, None
    today_fast = moving_average(closes, fast)
    today_slow = moving_average(closes, slow)
    prev_closes = closes[:-1]
    prev_fast = moving_average(prev_closes, fast)
    prev_slow = moving_average(prev_closes, slow)
    return today_fast, today_slow, prev_fast, prev_slow


def normalize_symbol(text: str) -> str:
    """把用户输入的 600519 / sh600519 / 000001.SZ 统一成 sh600519。"""
    s = text.strip().lower()
    if s.startswith(("sh", "sz", "bj")):
        return s
    for suffix in (".sh", ".sz", ".bj"):
        if s.endswith(suffix):
            return suffix[1:] + s[:-3]
    if s.isdigit() and len(s) == 6:
        if s.startswith(("60", "68", "9", "5", "11", "13")):
            return "sh" + s
        if s.startswith(("00", "30", "20", "15", "16", "12")):
            return "sz" + s
        if s.startswith(("83", "87", "43", "92")):
            return "bj" + s
        return "sh" + s
    return s


__all__ = [
    "Quote", "MarketClient", "parse_quote_line", "normalize_symbol",
    "moving_average", "ma_pair", "datetime",
]
