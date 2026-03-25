@echo off
chcp 65001 >nul
setlocal

cd /d "%~dp0"
set "EXE_PATH=%~dp0dist\TOOL TEST ACCOUNT.exe"

if not exist "%EXE_PATH%" (
    echo.
    echo Chua tim thay file EXE:
    echo %EXE_PATH%
    echo.
    echo Dang chuyen sang build EXE...
    call "%~dp0build_tool_test_account.bat"
    if errorlevel 1 (
        echo.
        echo Build that bai. Khong the chay tool.
        pause
        exit /b 1
    )
)

echo Dang mo TOOL TEST ACCOUNT...
start "" "%EXE_PATH%"

endlocal
