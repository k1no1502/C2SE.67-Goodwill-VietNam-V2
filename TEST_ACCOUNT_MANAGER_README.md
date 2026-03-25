# Test Account Manager - Goodwill Vietnam

Ứng dụng Python giúp quản lý và tự động đăng nhập vào 10 tài khoản kiểm thử (test1-test10).

## Tính năng

✅ Giao diện GUI dễ sử dụng với 10 nút tài khoản kiểm thử
✅ Tự động đăng nhập khi nhấp vào tài khoản
✅ Hiển thị tài khoản hiện tại đang đăng nhập
✅ Kiểm soát Chrome dùng Selenium
✅ Thông báo trạng thái real-time

## Yêu cầu hệ thống

- Python 3.8+
- Chrome/Chromium browser
- pip (Python package manager)

## Cài đặt

### 1. Cài đặt Python (nếu chưa có)

Tải Python từ https://www.python.org/downloads/

### 2. Cài đặt Dependencies

Mở Command Prompt/PowerShell tại folder `c:\xampp\htdocs\GW_VN Ver Final\` và chạy:

```bash
pip install -r requirements.txt
```

Hoặc cài đặt từng thư viện:

```bash
pip install selenium webdriver-manager
```

### 3. Chạy ứng dụng

```bash
cd c:\xampp\htdocs\GW_VN Ver Final
python test_account_manager.py
```

Hoặc nhấp đôi **test_account_manager.py**

## Cách sử dụng

1. Ứng dụng sẽ mở một cửa sổ giao diện
2. Nhấp vào bất kỳ nút tài khoản nào (TEST1 - TEST10)
3. Chrome sẽ tự động mở và đăng nhập vào tài khoản đó
4. Khi đăng nhập thành công, tên tài khoản sẽ hiển thị ở phía trên

## Cấu hình

Để thay đổi thông tin đăng nhập, chỉnh sửa các dòng này trong `test_account_manager.py`:

```python
WEBSITE_URL = "http://localhost/GW_VN%20Ver%20Final/"
PASSWORD = "123456"
```

## Các tài khoản kiểm thử

- test1@goodwillvietnam.com : 123456
- test2@goodwillvietnam.com : 123456
- test3@goodwillvietnam.com : 123456
- test4@goodwillvietnam.com : 123456
- test5@goodwillvietnam.com : 123456
- test6@goodwillvietnam.com : 123456
- test7@goodwillvietnam.com : 123456
- test8@goodwillvietnam.com : 123456
- test9@goodwillvietnam.com : 123456
- test10@goodwillvietnam.com : 123456

## Khắc phục sự cố

### Lỗi: "ChromeDriver not found"
- Đảm bảo Chrome được cài đặt
- Gỡ cài webdriver-manager và cài lại: `pip install webdriver-manager --force-reinstall`

### Lỗi: "Module not found"
- Chắc chắn đã chạy: `pip install -r requirements.txt`

### Lỗi: "Connection refused"
- Kiểm tra XAMPP đang chạy
- Kiểm tra website URL: http://localhost/GW_VN%20Ver%20Final/

## Ghi chú

- Ứng dụng sử dụng Selenium để tự động hóa quá trình đăng nhập
- Mỗi lần nhấp vào tài khoản, sẽ mở một Chrome instance mới
- Đóng ứng dụng sẽ tự động đóng tất cả Chrome windows
