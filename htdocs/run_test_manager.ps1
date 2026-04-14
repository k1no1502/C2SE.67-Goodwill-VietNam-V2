# Test Account Manager - Goodwill Vietnam
# PowerShell startup script

Write-Host "========================================" -ForegroundColor Cyan
Write-Host " Test Account Manager - Goodwill Vietnam" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Get script directory
$scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
Set-Location $scriptDir

# Check Python
Write-Host "Kiểm tra Python..." -ForegroundColor Yellow
$pythonCheck = python --version 2>$null

if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Lỗi: Python không được cài đặt hoặc không có trong PATH" -ForegroundColor Red
    Write-Host ""
    Write-Host "Vui lòng cài đặt Python từ https://www.python.org/downloads/" -ForegroundColor Yellow
    Write-Host "Hãy chắc chắn chọn 'Add Python to PATH' khi cài đặt" -ForegroundColor Yellow
    Write-Host ""
    Read-Host "Nhấn Enter để thoát"
    exit 1
}

Write-Host "✓ Python đã được tìm thấy ($pythonCheck)" -ForegroundColor Green
Write-Host ""

# Install dependencies
Write-Host "Cài đặt các thư viện cần thiết từ requirements.txt..." -ForegroundColor Yellow
python -m pip install -r requirements.txt --quiet

if ($LASTEXITCODE -ne 0) {
    Write-Host "❌ Lỗi cài đặt thư viện" -ForegroundColor Red
    Write-Host ""
    Read-Host "Nhấn Enter để thoát"
    exit 1
}

Write-Host "✓ Các thư viện đã được cài đặt thành công" -ForegroundColor Green
Write-Host ""
Write-Host "Đang khởi động ứng dụng..." -ForegroundColor Yellow
Write-Host ""

# Run the app
python "Test/test_account_manager.py"

if ($LASTEXITCODE -ne 0) {
    Write-Host ""
    Write-Host "❌ Lỗi khi chạy ứng dụng" -ForegroundColor Red
    Write-Host ""
    Read-Host "Nhấn Enter để thoát"
    exit 1
}
