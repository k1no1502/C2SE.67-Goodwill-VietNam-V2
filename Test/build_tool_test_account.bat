@echo off
chcp 65001 >nul
setlocal

echo.
echo ==========================================
echo   BUILD EXE - TOOL TEST ACCOUNT
echo ==========================================
echo.

cd /d "%~dp0"

echo [1/4] Kiem tra Python...
python --version >nul 2>&1
if errorlevel 1 (
    echo.
    echo [ERROR] Python chua duoc cai dat hoac chua co trong PATH.
    echo Cai Python tai: https://www.python.org/downloads/
    pause
    exit /b 1
)

echo [2/4] Cai dat/cap nhat dependencies...
python -m pip install --upgrade pip >nul 2>&1
python -m pip install -r "..\requirements.txt"
if errorlevel 1 (
    echo.
    echo [ERROR] Khong cai dat duoc dependencies.
    pause
    exit /b 1
)

echo [3/4] Cai dat PyInstaller...
python -m pip install pyinstaller
if errorlevel 1 (
    echo.
    echo [ERROR] Khong cai dat duoc PyInstaller.
    pause
    exit /b 1
)

echo [4/4] Build file EXE...
python -m PyInstaller ^
  --noconfirm ^
  --clean ^
  --onefile ^
  --windowed ^
  --name "TOOL TEST ACCOUNT" ^
  "test_account_manager.py"

if errorlevel 1 (
    echo.
    echo [ERROR] Build EXE that bai.
    pause
    exit /b 1
)

echo.
echo [SUCCESS] Da tao file EXE:
echo %~dp0dist\TOOL TEST ACCOUNT.exe
echo.
echo Nhan Enter de mo thu muc dist...
pause >nul
start "" "%~dp0dist"

endlocal
