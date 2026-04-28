# ✅ SỬA LỖI GIỎ HÀNG - QUANTITY = 100

## 🐛 **VẤN ĐỀ:**
- Khi nhấn "Thêm vào giỏ" → Số lượng tự động lên 100 thay vì 1
- Có thể do dữ liệu sai trong database hoặc logic tính toán sai

## 🔧 **ĐÃ SỬA:**

### 1. **Cập nhật `api/add-to-cart.php`:**
```php
// TRƯỚC (có thể gây lỗi):
$quantity = (int)($input['quantity'] ?? 1);
// Cộng thêm quantity vào cart hiện có
Database::execute("UPDATE cart SET quantity = quantity + ?", [$quantity]);

// SAU (đã sửa):
$quantity = 1; // Luôn thêm 1 sản phẩm mỗi lần nhấn
// Chỉ cộng thêm 1
Database::execute("UPDATE cart SET quantity = quantity + 1", []);
```

### 2. **Thêm validation trong `api/add-to-cart.php`:**
```php
// Kiểm tra số lượng có sẵn
$availableQuantity = $item['quantity'];
$currentCartQuantity = Database::fetch("SELECT COALESCE(SUM(quantity), 0) as total FROM cart WHERE user_id = ? AND item_id = ?", [$_SESSION['user_id'], $item_id])['total'];

if ($currentCartQuantity >= $availableQuantity) {
    throw new Exception('Số lượng trong giỏ hàng đã đạt tối đa có sẵn.');
}
```

### 3. **Cập nhật `cart.php` để tự động sửa lỗi:**
```php
// Fix any items with quantity > available_quantity
foreach ($cartItems as $item) {
    if ($item['quantity'] > $item['available_quantity']) {
        Database::execute(
            "UPDATE cart SET quantity = ? WHERE cart_id = ?",
            [$item['available_quantity'], $item['cart_id']]
        );
    }
}
```

### 4. **Cải thiện hiển thị trong `cart.php`:**
```php
// Đảm bảo available_quantity không âm
data-max="<?php echo max(1, $item['available_quantity']); ?>"
<?php echo $item['quantity'] >= max(1, $item['available_quantity']) ? 'disabled' : ''; ?>

// Hiển thị "Còn lại" đúng
Còn lại: <strong><?php echo max(0, $item['available_quantity']); ?></strong>
```

## 🧪 **CÁCH KIỂM TRA:**

### **1. Chạy script kiểm tra:**
```bash
# Mở trình duyệt:
http://localhost/Cap%201%20-%202/fix-cart.php
```

### **2. Reset giỏ hàng (nếu cần):**
```bash
# Mở trình duyệt:
http://localhost/Cap%201%20-%202/reset-cart.php
```

### **3. Test thêm vào giỏ:**
1. Vào Shop Bán Hàng
2. Nhấn "Thêm vào giỏ" 1 lần
3. Kiểm tra giỏ hàng → Số lượng phải là 1
4. Nhấn "Thêm vào giỏ" lần nữa → Số lượng phải là 2

## ✅ **KẾT QUẢ MONG ĐỢI:**

### **Trước khi sửa:**
- ❌ Nhấn "Thêm vào giỏ" → Quantity = 100
- ❌ Có thể vượt quá số lượng có sẵn

### **Sau khi sửa:**
- ✅ Nhấn "Thêm vào giỏ" → Quantity = 1
- ✅ Mỗi lần nhấn chỉ cộng thêm 1
- ✅ Không thể vượt quá số lượng có sẵn
- ✅ Tự động sửa lỗi nếu có dữ liệu sai

## 📝 **GHI CHÚ:**

- **Script `fix-cart.php`:** Kiểm tra và sửa dữ liệu sai
- **Script `reset-cart.php`:** Xóa toàn bộ giỏ hàng để test lại
- **Validation:** Ngăn chặn thêm quá số lượng có sẵn
- **Auto-fix:** Tự động sửa lỗi khi load trang giỏ hàng

## 🚀 **CÁCH SỬ DỤNG:**

1. **Xóa file test (sau khi sửa xong):**
   ```bash
   rm fix-cart.php
   rm reset-cart.php
   ```

2. **Test lại:**
   - Vào Shop → Thêm sản phẩm vào giỏ
   - Kiểm tra số lượng hiển thị đúng
   - Test tăng/giảm số lượng

**Made with ❤️ by Goodwill Vietnam**
