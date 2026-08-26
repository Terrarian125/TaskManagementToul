@echo off
cd /d "%~dp0"
if not exist Projects mkdir Projects
python server.py
pause
