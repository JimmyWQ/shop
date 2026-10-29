@echo off
chcp 65001 >nul
cd /d "%~dp0"
title Stock Monitor
echo Starting stock monitor (interval from config.json)...
echo Logs: monitor.log    Stop: create a file named STOP in this folder
E:\py\python.exe monitor.py
pause
