@echo off
chcp 65001 >nul
echo.
echo ========================================
echo  Test Account Manager - Goodwill Vietnam
echo ========================================
echo.

cd /d "%~dp0"

echo Kiểm tra Python...
python --version >nul 2>&1

if errorlevel 1 (
    echo ❌ Lỗi: Python không được cài đặt hoặc không có trong PATH
    echo.
    echo Vui lòng cài đặt Python từ https://www.python.org/downloads/
    echo Hãy chắc chắn chọn "Add Python to PATH" khi cài đặt
    pause
    exit /b 1
)

echo ✓ Python đã được tìm thấy
echo.
echo Cài đặt các thư viện cần thiết...
pip install -r requirements.txt

if errorlevel 1 (
    echo.
    echo ❌ Lỗi cài đặt thư viện
    pause
    exit /b 1
)

echo.
echo ✓ Các thư viện đã được cài đặt thành công
echo.
echo Đang khởi động ứng dụng...
echo.

python "Test\test_account_manager.py"

if errorlevel 1 (
    echo.
    echo ❌ Lỗi khi chạy ứng dụng
    pause
    exit /b 1
)

pause
