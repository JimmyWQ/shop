# -*- coding: utf-8 -*-
"""提醒通道：控制台 / CSV / 日志 / 桌面弹窗 / 声音 / Webhook。零第三方依赖。"""
from __future__ import annotations

import csv
import json
import os
import sys
import time
import urllib.request
from dataclasses import dataclass
from datetime import datetime
from typing import Iterable, List, Optional

CSV_HEADER = ["时间", "代码", "名称", "现价", "涨跌幅%", "规则", "说明"]

# 让 Windows 控制台能输出中文
if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8", errors="replace")
        sys.stderr.reconfigure(encoding="utf-8", errors="replace")
    except Exception:
        pass
    try:
        import ctypes
        ctypes.windll.kernel32.SetConsoleOutputCP(65001)
        ctypes.windll.kernel32.SetConsoleCP(65001)
    except Exception:
        pass


@dataclass
class Alert:
    symbol: str
    name: str
    price: float
    pct: float
    rule_id: str
    message: str
    ts: datetime = None

    def __post_init__(self):
        if self.ts is None:
            self.ts = datetime.now()

    def time_str(self) -> str:
        return self.ts.strftime("%Y-%m-%d %H:%M:%S")

    def title(self) -> str:
        arrow = "▲" if self.pct > 0 else ("▼" if self.pct < 0 else "—")
        return f"{self.name} {self.price:.2f} {arrow}{self.pct:+.2f}%"

    def to_row(self) -> List[str]:
        return [
            self.time_str(), self.symbol, self.name,
            f"{self.price:.3f}", f"{self.pct:+.2f}", self.rule_id, self.message,
        ]


class Notifier:
    def __init__(self, cfg: dict, base_dir: str):
        self.cfg = cfg or {}
        self.base_dir = base_dir
        self.desk_cfg = self.cfg.get("desktop", {}) or {}
        self.alert_csv = self._path(self.desk_cfg.get("csv_file", "alerts.csv"))
        self.log_file = self._path(self.desk_cfg.get("log_file", "monitor.log"))
        self._csv_ready = os.path.exists(self.alert_csv)
        self._queue: List[Alert] = []
        self._tk_root = None

    def _path(self, p: str) -> str:
        return p if os.path.isabs(p) else os.path.join(self.base_dir, p)

    # ---------- 基础通道 ----------
    def log(self, text: str) -> None:
        try:
            with open(self.log_file, "a", encoding="utf-8") as fh:
                fh.write(f"{datetime.now():%Y-%m-%d %H:%M:%S} {text}\n")
        except OSError:
            pass

    def console(self, alerts: Iterable[Alert]) -> None:
        for a in alerts:
            print(f"  [提醒] {a.time_str()} {a.title()}  |  {a.message}")

    def to_csv(self, alerts: Iterable[Alert]) -> None:
        if not self.desk_cfg.get("write_csv", True):
            return
        rows = [a.to_row() for a in alerts]
        if not rows:
            return
        try:
            with open(self.alert_csv, "a", encoding="utf-8-sig", newline="") as fh:
                w = csv.writer(fh)
                if not self._csv_ready:
                    w.writerow(CSV_HEADER)
                    self._csv_ready = True
                w.writerows(rows)
        except OSError as exc:
            self.log(f"写CSV失败: {exc}")

    # ---------- 桌面弹窗 ----------
    def _ensure_root(self):
        if self._tk_root is not None:
            return self._tk_root
        import tkinter as tk
        root = tk.Tk()
        root.withdraw()          # 隐藏主窗口，只用来做 after 调度
        self._tk_root = root
        return root

    def _show_window(self, alert: Alert, corner: str, width: int, height: int,
                     duration_ms: int, offset: int, opacity: float):
        import tkinter as tk

        bg, fg, fg2 = "#1f2430", "#ffffff", "#9aa4b2"
        accent = "#e5484d" if alert.pct < 0 else "#30a46c"

        win = tk.Toplevel(self._ensure_root())
        win.overrideredirect(True)
        win.configure(bg=bg)
        win.attributes("-topmost", True)
        try:
            win.attributes("-alpha", opacity)
        except tk.TclError:
            pass

        sw = win.winfo_screenwidth()
        if corner.endswith("right"):
            x = sw - width - 20
        else:
            x = 20
        if corner.startswith("top"):
            y = 20 + offset
        else:
            y = win.winfo_screenheight() - height - 20 - offset
        win.geometry(f"{width}x{height}+{x}+{y}")

        bar = tk.Frame(win, bg=accent, width=6)
        bar.pack(side="left", fill="y")

        box = tk.Frame(win, bg=bg, padx=14, pady=10)
        box.pack(side="left", fill="both", expand=True)

        tk.Label(box, text=alert.title(), font=("Microsoft YaHei UI", 12, "bold"),
                 fg=fg, bg=bg, anchor="w").pack(fill="x")
        tk.Label(box, text=alert.message, font=("Microsoft YaHei UI", 10),
                 fg=fg2, bg=bg, anchor="w", justify="left",
                 wraplength=width - 50).pack(fill="x", pady=(4, 2))
        tk.Label(box, text=alert.time_str(), font=("Microsoft YaHei UI", 8),
                 fg=fg2, bg=bg, anchor="w").pack(fill="x")

        def close():
            try:
                win.destroy()
            except tk.TclError:
                pass

        win.bind("<Button-1>", lambda _e: close())
        win.after(duration_ms, close)

    def _beep(self) -> None:
        snd = self.desk_cfg.get("sound", True)
        if not snd:
            return
        try:
            import winsound
            winsound.MessageBeep(-1)
        except Exception:
            pass

    def popup_oneshot(self, alerts: List[Alert]) -> None:
        """一次性模式：直接弹窗，阻塞到全部弹完。"""
        if not self.desk_cfg.get("enabled", False) or not alerts:
            return
        corner = self.desk_cfg.get("corner", "top-right")
        width = int(self.desk_cfg.get("width", 340))
        height = int(self.desk_cfg.get("height", 110))
        duration = int(self.desk_cfg.get("duration_ms", 6000))
        opacity = float(self.desk_cfg.get("opacity", 0.95))
        try:
            root = self._ensure_root()
            for idx, a in enumerate(alerts[:5]):   # 最多同时弹 5 个
                self._show_window(a, corner, width, height, duration, idx * (height + 10), opacity)
            self._beep()
            root.after(duration + 800, root.quit)
            root.mainloop()
        except Exception as exc:
            self.log(f"桌面弹窗失败: {exc}")

    def popup_daemon(self, alerts: List[Alert]) -> None:
        """常驻模式：交给 Tk 主线程的队列，由 pump 弹窗。"""
        if not self.desk_cfg.get("enabled", False):
            return
        for a in alerts:
            self._queue.append(a)
        self._beep()

    def pump(self) -> None:
        """由 Tk 主循环每 300ms 调用一次，弹出队列中的提醒。"""
        if not self._queue:
            return
        root = self._ensure_root()
        corner = self.desk_cfg.get("corner", "top-right")
        width = int(self.desk_cfg.get("width", 340))
        height = int(self.desk_cfg.get("height", 110))
        duration = int(self.desk_cfg.get("duration_ms", 6000))
        opacity = float(self.desk_cfg.get("opacity", 0.95))
        batch = self._queue[:5]
        self._queue = self._queue[5:]
        for idx, a in enumerate(batch):
            try:
                self._show_window(a, corner, width, height, duration, idx * (height + 10), opacity)
            except Exception as exc:
                self.log(f"桌面弹窗失败: {exc}")
        root.after(300, self.pump)

    # ---------- Webhook ----------
    def webhook(self, alerts: List[Alert]) -> None:
        wh = (self.cfg.get("webhook") or {})
        url = (wh.get("url") or "").strip()
        if not wh.get("enabled") or not url or not alerts:
            return

        lines = [f"【{a.time_str()}】{a.title()}  {a.message}" for a in alerts]
        payload = {
            "text": "\n".join(lines),
            "title": "股票提醒",
            "alerts": [a.to_row() for a in alerts],
        }
        tmpl = wh.get("body_template") or ""
        if tmpl:
            try:
                text = tmpl.replace("{text}", json.dumps("")
                                    if False else "\n".join(lines))
                body = text.encode("utf-8")
            except Exception:
                body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        else:
            body = json.dumps(payload, ensure_ascii=False).encode("utf-8")

        headers = {"Content-Type": "application/json; charset=utf-8"}
        headers.update(wh.get("headers") or {})
        method = (wh.get("method") or "POST").upper()

        for attempt in range(int(wh.get("retries", 2)) + 1):
            try:
                req = urllib.request.Request(url, data=body, headers=headers, method=method)
                with urllib.request.urlopen(req, timeout=10) as resp:
                    code = resp.status
                self.log(f"Webhook 已推送 {len(alerts)} 条, HTTP {code}")
                return
            except Exception as exc:
                if attempt >= int(wh.get("retries", 2)):
                    self.log(f"Webhook 失败: {exc}")
                else:
                    time.sleep(1.5)

    # ---------- 一站式 ----------
    def dispatch(self, alerts: List[Alert], daemon: bool = False) -> None:
        if not alerts:
            return
        self.console(alerts)
        self.to_csv(alerts)
        for a in alerts:
            self.log(f"{a.rule_id} | {a.symbol} {a.name} {a.price:.3f} {a.pct:+.2f}% | {a.message}")
        self.webhook(alerts)
        if daemon:
            self.popup_daemon(alerts)
        else:
            self.popup_oneshot(alerts)
