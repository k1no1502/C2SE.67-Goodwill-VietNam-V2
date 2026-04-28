@echo off
echo Installing required Python libraries...
pip install -r requirements.txt
echo.
echo Starting Web AI Moderation Listener...
python web.py
pause
