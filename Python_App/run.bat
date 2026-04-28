@echo off
setlocal enabledelayedexpansion

echo ======================================================
echo   DANG DONG GOI UNG DUNG THU NGAN (PHAN BAN MOI)
echo ======================================================

:: 1. Tat tat ca moi thu lien quan
echo [1/4] Dang dung cac tien trinh...
taskkill /F /IM Goodwill_Cashier.exe /T >nul 2>&1
taskkill /F /IM Goodwill_Vietnam_POS.exe /T >nul 2>&1
taskkill /F /FI "IMAGENAME eq Goodwill*" /T >nul 2>&1
timeout /t 5 >nul

:: 2. Xoa cac file cu de fix loi Permission
echo [2/4] Dang lam sach bo nho tam...
powershell -Command "if (Test-Path build) { Remove-Item -Recurse -Force build }" >nul 2>&1
powershell -Command "if (Test-Path dist) { Remove-Item -Recurse -Force dist }" >nul 2>&1
timeout /t 2 >nul

:: 3. Cai dat thu vien
python -m pip install -r requirements.txt --quiet

:: 4. Tao file EXE voi ten MOI de khong bi trung file dang khoa
echo [3/4] Dang tao file EXE moi (Vui long doi)...
:: Lay duong dan thu vien tu python de copy DLL chinh xac
for /f "tokens=*" %%i in ('python -c "import pyzbar, os; print(os.path.dirname(pyzbar.__file__))"') do set PYZBAR_PATH=%%i

python -m PyInstaller --noconsole --onefile --clean ^
    --add-binary "%PYZBAR_PATH%\libiconv.dll;." ^
    --add-binary "%PYZBAR_PATH%\libzbar-64.dll;." ^
    --name "Goodwill_Vietnam_POS" main.py

if %errorlevel% equ 0 (
    echo.
    echo ======================================================
    echo   THANH CONG! File .exe moi la: Goodwill_Vietnam_POS.exe
    echo   DANG MO UNG DUNG...
    echo ======================================================
    start "" "dist\Goodwill_Vietnam_POS.exe"
) else (
    echo.
    echo [LOI] Van bi loi Permission Error. 
    echo ==> CACH FIX: BAN HAY KHOI DONG LAI MAY TINH DE GIAI PHONG FILE BI KET.
)

pause
