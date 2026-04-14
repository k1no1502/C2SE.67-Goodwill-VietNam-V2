<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Noi dung khong duoc de trong.'
    ]);
    exit();
}

function contains_any_keyword($text, array $keywords) {
    foreach ($keywords as $keyword) {
        if ($keyword !== '' && mb_strpos($text, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

$normalizedMessage = mb_strtolower($message, 'UTF-8');

$isAdvisorIdentityQuestion = (
    contains_any_keyword($normalizedMessage, ['nhân viên tư vấn', 'nhan vien tu van'])
    && contains_any_keyword($normalizedMessage, ['goodwill việt nam', 'goodwill viet nam', 'gôdwill việt nam', 'gôdwill viet nam', 'godwill việt nam', 'godwill viet nam'])
) || contains_any_keyword($normalizedMessage, [
    'bạn có phải là nhân viên tư vấn của gôdwill việt nam không',
    'ban co phai la nhan vien tu van cua godwill viet nam khong',
    'bạn có phải là nhân viên tư vấn của goodwill việt nam không',
    'ban co phai la nhan vien tu van cua goodwill viet nam khong'
]);

if ($isAdvisorIdentityQuestion) {
    echo json_encode([
        'success' => true,
        'reply' => 'Mình là trợ lý AI của GoodWill Việt Nam, không phải nhân viên tư vấn trực tiếp. Nếu bạn muốn trao đổi với nhân viên tư vấn, bạn có thể chuyển sang chế độ chat với nhân viên tư vấn trong hộp chat này.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['quy trình các bước quyên góp', 'quy trinh cac buoc quyen gop', 'quy trình quyên góp', 'quy trinh quyen gop', 'các bước quyên góp', 'cac buoc quyen gop'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Quy trình quyên góp gồm các bước:\n\n1. Mở trang [Quyên góp](/donate.php)\n2. Chọn chiến dịch muốn ủng hộ\n3. Nhập số tiền quyên góp\n4. Chọn phương thức thanh toán\n5. Xác nhận và hoàn tất giao dịch\n6. Xem lại tại [Lịch sử quyên góp](/my-donations.php)\n\nBạn có thể nhấn vào [đường dẫn này](/donate.php) để bắt đầu ngay."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['quy trình các bước tạo chiến dịch', 'quy trinh cac buoc tao chien dich', 'quy trình tạo chiến dịch', 'quy trinh tao chien dich', 'taoh chiến dịch', 'taoh chien dich', 'tạo chiến dịch', 'tao chien dich'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Quy trình tạo chiến dịch gồm các bước:\n\n1. Mở trang [Tạo chiến dịch](/create-campaign.php)\n2. Nhập tiêu đề, mô tả chiến dịch\n3. Thêm hình ảnh/video minh họa\n4. Đặt mục tiêu và thời gian chiến dịch\n5. Gửi tạo chiến dịch\n6. Theo dõi tại [Danh sách chiến dịch](/campaigns.php)\n\nBạn có thể nhấn vào [đường dẫn này](/create-campaign.php) để tạo chiến dịch ngay."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['mua hàng', 'mua hang', 'quy trình mua hàng', 'quy trinh mua hang', 'các bước mua hàng', 'cac buoc mua hang'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Các bước mua hàng trên hệ thống:\n\n1. Vào [Shop](/shop.php)\n2. Chọn sản phẩm cần mua\n3. Thêm vào giỏ hàng\n4. Mở [Giỏ hàng](/cart.php) để kiểm tra\n5. Nhấn thanh toán và điền thông tin nhận hàng\n6. Hoàn tất đơn\n\nBạn có thể bắt đầu tại [đường dẫn này](/shop.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['xem đơn', 'xem don', 'xem đơn hàng', 'xem don hang', 'kiểm tra đơn', 'kiem tra don', 'theo dõi đơn', 'theo doi don'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/my-orders.php) để xem danh sách đơn và trạng thái từng đơn.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['xem lịch sử đơn', 'xem lich su don', 'xem lịch sử đơn hàng', 'xem lich su don hang', 'lịch sử đơn', 'lich su don'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/my-orders.php) để xem toàn bộ lịch sử đơn hàng của bạn.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['lịch sử quyên góp', 'lich su quyen gop', 'xem lịch sử quyên góp', 'xem lich su quyen gop', 'lịch sử quyên góp của tôi', 'lich su quyen gop cua toi', 'quyên góp của tôi', 'quyen gop cua toi', 'my donations'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/my-donations.php) để xem lịch sử quyên góp của bạn.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['lịch sử đơn hàng', 'lich su don hang', 'đơn hàng của tôi', 'don hang cua toi', 'xem đơn hàng', 'xem don hang', 'my orders', 'order history'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/my-orders.php) để xem đơn hàng của bạn.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['hồ sơ của tôi', 'ho so cua toi', 'hồ sơ', 'ho so', 'profile', 'tài khoản', 'tai khoan'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/profile.php) để vào trang hồ sơ của bạn.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['xem phản hồi', 'xem phan hoi', 'phản hồi', 'phan hoi', 'feedback', 'đánh giá', 'danh gia'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/feedback.php) để vào trang phản hồi.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['đổi mật khẩu', 'doi mat khau', 'thay đổi mật khẩu', 'thay doi mat khau', 'change password', 'password'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/change-password.php) để vào trang đổi mật khẩu.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['làm sao quyên góp', 'lam sao quyen gop', 'cách quyên góp', 'cach quyen gop', 'hướng dẫn quyên góp', 'huong dan quyen gop', 'bước quyên góp', 'buoc quyen gop'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Dưới đây là các bước quyên góp:\n\n1. Truy cập trang [quyên góp](/donate.php)\n2. Chọn chiến dịch hoặc mục đích quyên góp\n3. Nhập số tiền muốn quyên góp\n4. Chọn phương thức thanh toán\n5. Hoàn tất giao dịch\n\nBạn có thể nhấn vào [đường dẫn này](/donate.php) để bắt đầu quyên góp ngay."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['làm sao tạo chiến dịch', 'lam sao tao chien dich', 'cách tạo chiến dịch', 'cach tao chien dich', 'hướng dẫn tạo chiến dịch', 'huong dan tao chien dich', 'bước tạo chiến dịch', 'buoc tao chien dich'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Dưới đây là các bước tạo chiến dịch:\n\n1. Truy cập trang [tạo chiến dịch](/create-campaign.php)\n2. Điền thông tin cơ bản về chiến dịch (tiêu đề, mô tả)\n3. Tải lên hình ảnh đại diện cho chiến dịch\n4. Đặt mục tiêu tài chính (nếu có)\n5. Chọn danh mục phù hợp\n6. Nhấn nút Tạo để hoàn tất\n\nBạn có thể nhấn vào [đường dẫn này](/create-campaign.php) để tạo chiến dịch ngay."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['làm sao tham gia tình nguyện viên', 'lam sao tham gia tinh nguyen vien', 'cách tham gia tình nguyện viên', 'cach tham gia tinh nguyen vien', 'hướng dẫn tình nguyện viên', 'huong dan tinh nguyen vien', 'bước tham gia tình nguyện', 'buoc tham gia tinh nguyen'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Dưới đây là các bước tham gia tình nguyện viên:\n\n1. Truy cập trang [tình nguyện viên](/volunteer.php)\n2. Xem danh sách các vị trí tình nguyện hiện có\n3. Chọn vị trí bạn quan tâm\n4. Điền thông tin cá nhân và lý do tham gia\n5. Gửi đơn xin tham gia\n6. Chờ xác nhận từ admin\n\nBạn có thể nhấn vào [đường dẫn này](/volunteer.php) để tham gia tình nguyện ngay."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['làm sao mua sắm', 'lam sao mua sam', 'cách mua sắm', 'cach mua sam', 'hướng dẫn mua sắm', 'huong dan mua sam', 'bước mua sắm', 'buoc mua sam', 'làm sao mua hàng', 'lam sao mua hang'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Dưới đây là các bước mua sắm trên shop:\n\n1. Truy cập trang [Shop](/shop.php)\n2. Duyệt danh sách sản phẩm hoặc sử dụng tìm kiếm\n3. Chọn sản phẩm bạn muốn mua\n4. Xem chi tiết sản phẩm và giá cả\n5. Nhấn Thêm vào giỏ hàng\n6. Tiến hành thanh toán\n7. Hoàn tất đơn hàng\n\nBạn có thể nhấn vào [đường dẫn này](/shop.php) để bắt đầu mua sắm ngay."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── ENGLISH / SONG NGỮ: What is … ────────────────────────────────────────

if (contains_any_keyword($normalizedMessage, ['what is donation', 'what are donations', 'explain donation', 'tell me about donation', 'donation là gì', 'donation la gi', 'quyên góp là gì', 'quyen gop la gi'])) {
    echo json_encode([
        'success' => true,
        'reply' => "**Donation / Quyên góp** là hoạt động đóng góp đồ vật, hiện vật để hỗ trợ cộng đồng và những người cần giúp đỡ.\n\n🇻🇳 **Cách hoạt động:**\n- Bạn quyên góp đồ vật (quần áo, điện tử, sách, v.v.)\n- Nhân viên xét duyệt và chấp nhận đồ quyên góp\n- Đồ được thêm vào kho và phân phát hoặc bán với giá thấp\n- Xem lại tại [Lịch sử quyên góp](/my-donations.php)\n\n🇬🇧 **How it works:**\n- Donate physical items (clothes, electronics, books, etc.)\n- Staff review and approve each donation\n- Approved items go into inventory for distribution or resale\n- Track history at [My Donations](/my-donations.php)\n\nBắt đầu ngay / Get started: [Donate](/donate.php)"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['what is campaign', 'what are campaigns', 'explain campaign', 'tell me about campaign', 'campaign là gì', 'campaign la gi', 'chiến dịch là gì', 'chien dich la gi'])) {
    echo json_encode([
        'success' => true,
        'reply' => "**Campaign / Chiến dịch** là chương trình từ thiện nhằm thu hút quyên góp và tình nguyện viên cho một mục tiêu cụ thể.\n\n🇻🇳 **Chiến dịch gồm:**\n- Mục tiêu (số tiền hoặc số đồ vật cần thu thập)\n- Thời hạn rõ ràng\n- Tình nguyện viên tham gia hỗ trợ\n- Bất kỳ ai cũng có thể tạo và tham gia chiến dịch\n\n🇬🇧 **Campaigns include:**\n- A fundraising / item-collection goal with a deadline\n- Volunteer slots to help run the campaign\n- Anyone can create or join a campaign\n\nXem chiến dịch / Browse campaigns: [Campaigns](/campaigns.php)\nTạo chiến dịch / Create one: [Create Campaign](/create-campaign.php)"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['what is shop', 'what is the shop', 'explain shop', 'tell me about shop', 'what is store', 'what can i buy', 'shop là gì', 'shop la gi', 'cửa hàng là gì', 'cua hang la gi'])) {
    echo json_encode([
        'success' => true,
        'reply' => "**Shop / Cửa hàng** là chợ từ thiện nơi đồ quyên góp được bán lại với giá thấp hoặc miễn phí.\n\n🇻🇳 **Đặc điểm:**\n- Sản phẩm đến từ đồ quyên góp đã được duyệt\n- 3 mức giá: Miễn phí 🆓 | Giá rẻ 💛 | Giá thường 🏷️\n- Doanh thu hỗ trợ ngược lại các hoạt động từ thiện\n\n🇬🇧 **Features:**\n- Products sourced from approved donations\n- Price tiers: Free 🆓 | Discounted 💛 | Regular 🏷️\n- Revenue funds charitable activities\n\nVào mua sắm / Start shopping: [Shop](/shop.php)"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['what is volunteer', 'what is volunteering', 'explain volunteer', 'tell me about volunteer', 'what does volunteer mean', 'volunteer là gì', 'volunteer la gi', 'tình nguyện là gì', 'tinh nguyen la gi'])) {
    echo json_encode([
        'success' => true,
        'reply' => "**Volunteer / Tình nguyện viên** là người tham gia hỗ trợ các chiến dịch từ thiện của GoodWill Việt Nam.\n\n🇻🇳 **Tình nguyện viên có thể:**\n- Hỗ trợ tại chỗ, trực tuyến, hoặc vận chuyển hàng\n- Đăng ký tham gia các task trong chiến dịch\n- Ghi nhận giờ tình nguyện được xác nhận bởi quản lý\n\n🇬🇧 **Volunteers can:**\n- Help on-site, online, or with logistics\n- Sign up for specific tasks within a campaign\n- Have volunteer hours tracked and verified\n\nĐăng ký tình nguyện / Apply now: [Volunteer](/volunteer.php)"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['what is goodwill', 'what is goodwill vietnam', 'about goodwill', 'tell me about goodwill', 'goodwill là gì', 'goodwill la gi', 'giới thiệu', 'gioi thieu', 'about us', 'về goodwill', 've goodwill'])) {
    echo json_encode([
        'success' => true,
        'reply' => "**GoodWill Việt Nam** là nền tảng từ thiện kết nối người quyên góp, tình nguyện viên và người cần giúp đỡ.\n\n🎯 **Sứ mệnh / Mission:**\n- Kết nối cộng đồng qua sẻ chia\n- Làm cho từ thiện đơn giản, minh bạch và ý nghĩa\n\n📌 **Bạn có thể / You can:**\n- 🛍️ Mua đồ với giá thấp — [Shop](/shop.php)\n- 💝 Quyên góp đồ vật — [Donate](/donate.php)\n- 📣 Tạo / tham gia chiến dịch — [Campaigns](/campaigns.php)\n- 🤝 Đăng ký tình nguyện — [Volunteer](/volunteer.php)\n\nTìm hiểu thêm / Learn more: [About](/about.php)"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── ENGLISH / SONG NGỮ: Greetings ────────────────────────────────────────

if (contains_any_keyword($normalizedMessage, ['hello', 'hi there', 'hey', 'good morning', 'good afternoon', 'good evening', 'xin chào', 'xin chao', 'chào bạn', 'chao ban'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Xin chào! 👋 Mình là trợ lý AI của **GoodWill Việt Nam**.\n\nMình có thể giúp bạn:\n- 💝 [Quyên góp](/donate.php) — Donate goods\n- 🛍️ [Mua sắm](/shop.php) — Shop donated items\n- 📣 [Chiến dịch](/campaigns.php) — Browse campaigns\n- 🤝 [Tình nguyện](/volunteer.php) — Volunteer opportunities\n\nBạn muốn hỏi gì không? / How can I help you today?"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ── ENGLISH / SONG NGỮ: Account & navigation ─────────────────────────────

if (contains_any_keyword($normalizedMessage, ['login', 'sign in', 'log in', 'đăng nhập', 'dang nhap'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Bạn có thể đăng nhập tại [đây](/login.php).\n\nIf you forgot your password, click [Forgot Password](/forgot-password.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['register', 'sign up', 'create account', 'đăng ký', 'dang ky', 'tạo tài khoản', 'tao tai khoan'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Bạn có thể tạo tài khoản mới tại [đây](/register.php).\n\nRegistration is free and only takes a minute! Click [Register](/register.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['forgot password', 'reset password', 'quên mật khẩu', 'quen mat khau'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Bạn có thể đặt lại mật khẩu tại [Quên mật khẩu](/forgot-password.php).\n\nClick [Forgot Password](/forgot-password.php) to receive a reset link via email."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['contact', 'liên hệ', 'lien he', 'hỗ trợ kỹ thuật', 'ho tro ky thuat', 'support team'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Bạn có thể liên hệ hỗ trợ qua:\n\n📧 Email: info@goodwillvietnam.com\n📞 Hotline: +84 123 456 789\n💬 Chat với nhân viên ngay trong hộp chat này\n📝 Gửi phản hồi: [Feedback](/feedback.php)\n\nFor support in English, use our [Feedback / Contact form](/feedback.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['faq', 'frequently asked', 'câu hỏi thường gặp', 'cau hoi thuong gap', 'thường gặp', 'thuong gap'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Xem các câu hỏi thường gặp tại [FAQ](/faq.php).\n\nFor frequently asked questions, visit our [FAQ page](/faq.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['recruitment', 'tuyển dụng', 'tuyen dung', 'apply for job', 'ứng tuyển', 'ung tuyen', 'việc làm', 'viec lam'])) {
    echo json_encode([
        'success' => true,
        'reply' => "GoodWill Việt Nam đang tuyển dụng! 🎉\n\nXem các vị trí và nộp đơn tại [Tuyển dụng](/recruitment.php).\n\nWe're hiring! Browse open positions and apply at [Recruitment](/recruitment.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['my tasks', 'nhiệm vụ của tôi', 'nhiem vu cua toi', 'công việc của tôi', 'cong viec cua toi'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Xem danh sách nhiệm vụ của bạn tại [Nhiệm vụ của tôi](/my-tasks.php).\n\nView your assigned volunteer tasks at [My Tasks](/my-tasks.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['notification', 'thông báo', 'thong bao', 'alert', 'tin nhắn hệ thống', 'tin nhan he thong'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Xem thông báo của bạn tại [Thông báo](/notifications.php).\n\nCheck your latest notifications at [Notifications](/notifications.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['cart', 'giỏ hàng', 'gio hang', 'basket', 'checkout', 'thanh toán', 'thanh toan'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Xem giỏ hàng tại [Giỏ hàng](/cart.php). Khi sẵn sàng, tiến hành [Thanh toán](/checkout.php).\n\nView your cart at [Cart](/cart.php). When ready, proceed to [Checkout](/checkout.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['track order', 'theo dõi đơn hàng', 'theo doi don hang', 'order status', 'trạng thái đơn', 'trang thai don'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Theo dõi trạng thái đơn hàng tại [Đơn hàng của tôi](/my-orders.php).\n\nTrack your order status at [My Orders](/my-orders.php)."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ─────────────────────────────────────────────────────────────────────────

if (contains_any_keyword($normalizedMessage, ['quyên góp', 'quyen gop', 'ủng hộ', 'ung ho', 'donate'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/donate.php) để dẫn đến trang quyên góp.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['tạo chiến dịch', 'tao chien dich', 'create campaign', 'chiến dịch mới', 'chien dich moi'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/create-campaign.php) để dẫn đến trang tạo chiến dịch.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['shop', 'cửa hàng', 'cua hang', 'mua hàng', 'mua hang', 'mua', 'vat dung', 'vật dụng', 'do dung', 'đồ dùng', 'bán hàng', 'ban hang'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/shop.php) để dẫn đến trang Shop bán hàng.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword($normalizedMessage, ['tình nguyện viên', 'tinh nguyen vien', 'tham gia tình nguyện', 'tham gia tinh nguyen', 'tham gia chiến dịch', 'tham gia chien dich', 'volunteer'])) {
    echo json_encode([
        'success' => true,
        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/volunteer.php) để dẫn đến trang tham gia tình nguyện viên.'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$config = require __DIR__ . '/../config/google.php';
$apiKey = trim((string)($config['gemini_api_key'] ?? ''));

if ($apiKey === '') {
    echo json_encode([
        'success' => false,
        'message' => 'He thong chua cau hinh Gemini API key.'
    ]);
    exit();
}

if (!isset($_SESSION['gemini_chat_history']) || !is_array($_SESSION['gemini_chat_history'])) {
    $_SESSION['gemini_chat_history'] = [];
}

$history = $_SESSION['gemini_chat_history'];
$history[] = [
    'role' => 'user',
    'parts' => [
        ['text' => $message]
    ]
];

$payload = [
    'system_instruction' => [
        'parts' => [
            [
                'text' => 'Ban la tro ly GoodWill Viet Nam. Tra loi lich su, ngan gon, de hieu, uu tien tieng Viet. Neu can, hoi them thong tin de ho tro dung nhu cau nguoi dung.'
            ]
        ]
    ],
    'contents' => $history,
    'generationConfig' => [
        'temperature' => 0.7,
        'maxOutputTokens' => 512
    ]
];

$modelCandidates = [
    'gemini-2.0-flash',
    'gemini-2.0-flash-lite',
    'gemini-1.5-flash-latest',
    'gemini-1.5-flash'
];

$responseData = null;
$lastError = '';

foreach ($modelCandidates as $modelName) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($modelName) . ':generateContent?key=' . urlencode($apiKey);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        $lastError = 'Khong ket noi duoc Gemini API: ' . $curlError;
        continue;
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        $lastError = 'Phan hoi Gemini khong hop le.';
        continue;
    }

    if ($httpCode < 400 && !isset($decoded['error'])) {
        $responseData = $decoded;
        break;
    }

    $apiError = (string)($decoded['error']['message'] ?? ('HTTP ' . $httpCode));
    $lastError = 'Gemini loi: ' . $apiError;

    // If model/version is not found, try the next candidate.
    if (stripos($apiError, 'not found') !== false || stripos($apiError, 'not supported') !== false) {
        continue;
    }

    // For other API errors, stop early.
    break;
}

if (!is_array($responseData)) {
    if ($lastError !== '' && (stripos($lastError, 'quota') !== false || stripos($lastError, 'rate limit') !== false || stripos($lastError, 'resource_exhausted') !== false)) {
        if (contains_any_keyword($normalizedMessage, ['mua', 'shop', 'vat dung', 'vật dụng', 'do dung', 'đồ dùng', 'cửa hàng', 'cua hang'])) {
            echo json_encode([
                'success' => true,
                'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/shop.php) để vào trang Shop mua vật dụng.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['lịch sử đơn hàng', 'lich su don hang', 'đơn hàng của tôi', 'don hang cua toi', 'xem đơn hàng', 'xem don hang', 'my orders', 'order history'])) {
            echo json_encode([
                'success' => true,
                'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/my-orders.php) để xem đơn hàng của bạn.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['lịch sử quyên góp', 'lich su quyen gop', 'xem lịch sử quyên góp', 'xem lich su quyen gop', 'lịch sử quyên góp của tôi', 'lich su quyen gop cua toi', 'quyên góp của tôi', 'quyen gop cua toi', 'my donations'])) {
            echo json_encode([
                'success' => true,
                'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/my-donations.php) để xem lịch sử quyên góp của bạn.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['hồ sơ của tôi', 'ho so cua toi', 'hồ sơ', 'ho so', 'profile', 'tài khoản', 'tai khoan'])) {
            echo json_encode([
                'success' => true,
                'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/profile.php) để vào trang hồ sơ của bạn.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['xem phản hồi', 'xem phan hoi', 'phản hồi', 'phan hoi', 'feedback', 'đánh giá', 'danh gia'])) {
            echo json_encode([
                'success' => true,
                'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/feedback.php) để vào trang phản hồi.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['đổi mật khẩu', 'doi mat khau', 'thay đổi mật khẩu', 'thay doi mat khau', 'change password', 'password'])) {
            echo json_encode([
                'success' => true,
                'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/change-password.php) để vào trang đổi mật khẩu.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['làm sao quyên góp', 'lam sao quyen gop', 'cách quyên góp', 'cach quyen gop', 'hướng dẫn quyên góp', 'huong dan quyen gop', 'bước quyên góp', 'buoc quyen gop'])) {
            echo json_encode([
                'success' => true,
                'reply' => "Dưới đây là các bước quyên góp:\n\n1. Truy cập trang [quyên góp](/donate.php)\n2. Chọn chiến dịch hoặc mục đích quyên góp\n3. Nhập số tiền muốn quyên góp\n4. Chọn phương thức thanh toán\n5. Hoàn tất giao dịch\n\nBạn có thể nhấn vào [đường dẫn này](/donate.php) để bắt đầu quyên góp ngay."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['làm sao tạo chiến dịch', 'lam sao tao chien dich', 'cách tạo chiến dịch', 'cach tao chien dich', 'hướng dẫn tạo chiến dịch', 'huong dan tao chien dich', 'bước tạo chiến dịch', 'buoc tao chien dich'])) {
            echo json_encode([
                'success' => true,
                'reply' => "Dưới đây là các bước tạo chiến dịch:\n\n1. Truy cập trang [tạo chiến dịch](/create-campaign.php)\n2. Điền thông tin cơ bản về chiến dịch (tiêu đề, mô tả)\n3. Tải lên hình ảnh đại diện cho chiến dịch\n4. Đặt mục tiêu tài chính (nếu có)\n5. Chọn danh mục phù hợp\n6. Nhấn nút Tạo để hoàn tất\n\nBạn có thể nhấn vào [đường dẫn này](/create-campaign.php) để tạo chiến dịch ngay."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['làm sao tham gia tình nguyện viên', 'lam sao tham gia tinh nguyen vien', 'cách tham gia tình nguyện viên', 'cach tham gia tinh nguyen vien', 'hướng dẫn tình nguyện viên', 'huong dan tinh nguyen vien', 'bước tham gia tình nguyện', 'buoc tham gia tinh nguyen'])) {
            echo json_encode([
                'success' => true,
                'reply' => "Dưới đây là các bước tham gia tình nguyện viên:\n\n1. Truy cập trang [tình nguyện viên](/volunteer.php)\n2. Xem danh sách các vị trí tình nguyện hiện có\n3. Chọn vị trí bạn quan tâm\n4. Điền thông tin cá nhân và lý do tham gia\n5. Gửi đơn xin tham gia\n6. Chờ xác nhận từ admin\n\nBạn có thể nhấn vào [đường dẫn này](/volunteer.php) để tham gia tình nguyện ngay."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['làm sao mua sắm', 'lam sao mua sam', 'cách mua sắm', 'cach mua sam', 'hướng dẫn mua sắm', 'huong dan mua sam', 'bước mua sắm', 'buoc mua sam', 'làm sao mua hàng', 'lam sao mua hang'])) {
            echo json_encode([
                'success' => true,
                'reply' => "Dưới đây là các bước mua sắm trên shop:\n\n1. Truy cập trang [Shop](/shop.php)\n2. Duyệt danh sách sản phẩm hoặc sử dụng tìm kiếm\n3. Chọn sản phẩm bạn muốn mua\n4. Xem chi tiết sản phẩm và giá cả\n5. Nhấn Thêm vào giỏ hàng\n6. Tiến hành thanh toán\n7. Hoàn tất đơn hàng\n\nBạn có thể nhấn vào [đường dẫn này](/shop.php) để bắt đầu mua sắm ngay."
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        if (contains_any_keyword($normalizedMessage, ['tình nguyện viên', 'tinh nguyen vien', 'tham gia tình nguyện', 'tham gia tinh nguyen', 'tham gia chiến dịch', 'tham gia chien dich', 'volunteer'])) {
            echo json_encode([
                'success' => true,
                'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/volunteer.php) để vào trang tham gia tình nguyện viên.'
            ], JSON_UNESCAPED_UNICODE);
            exit();
        }

        echo json_encode([
            'success' => true,
            'reply' => 'Hiện chatbot AI đang quá tải tạm thời. Bạn thử lại sau ít phút giúp mình nhé.'
                if (contains_any_keyword($normalizedMessage, ['tình nguyện viên', 'tinh nguyen vien', 'tham gia tình nguyện', 'tham gia tinh nguyen', 'tham gia chiến dịch', 'tham gia chien dich', 'volunteer'])) {
                    echo json_encode([
                        'success' => true,
                        'reply' => 'Bạn có thể nhấn vào [đường dẫn này](/volunteer.php) để vào trang tham gia tình nguyện viên.'
                    ], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                // Quota fallback — English / bilingual handlers
                if (contains_any_keyword($normalizedMessage, ['what is donation', 'what are donations', 'explain donation', 'tell me about donation', 'donation là gì', 'donation la gi', 'quyên góp là gì', 'quyen gop la gi'])) {
                    echo json_encode(['success' => true, 'reply' => "**Donation / Quyên góp** — Đóng góp hiện vật hỗ trợ cộng đồng.\n\n- Donate items → staff review → added to inventory\n- Track donations: [My Donations](/my-donations.php)\n\nGet started: [Donate](/donate.php)"], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['what is campaign', 'what are campaigns', 'explain campaign', 'tell me about campaign', 'campaign là gì', 'chiến dịch là gì', 'chien dich la gi'])) {
                    echo json_encode(['success' => true, 'reply' => "**Campaign / Chiến dịch** — Chương trình thu hút quyên góp và tình nguyện với mục tiêu cụ thể.\n\nBrowse: [Campaigns](/campaigns.php) | Create: [New Campaign](/create-campaign.php)"], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['what is shop', 'what is the shop', 'explain shop', 'what can i buy', 'shop là gì', 'cửa hàng là gì'])) {
                    echo json_encode(['success' => true, 'reply' => "**Shop / Cửa hàng** — Chợ từ thiện bán đồ quyên góp: Miễn phí 🆓 | Giá rẻ 💛 | Giá thường 🏷️\n\nStart shopping: [Shop](/shop.php)"], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['what is volunteer', 'what is volunteering', 'explain volunteer', 'volunteer là gì', 'tình nguyện là gì', 'tinh nguyen la gi'])) {
                    echo json_encode(['success' => true, 'reply' => "**Volunteer / Tình nguyện viên** — Tham gia hỗ trợ các chiến dịch từ thiện của GoodWill.\n\nApply now: [Volunteer](/volunteer.php)"], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['what is goodwill', 'about goodwill', 'giới thiệu', 'gioi thieu', 'about us'])) {
                    echo json_encode(['success' => true, 'reply' => "**GoodWill Việt Nam** — Nền tảng từ thiện kết nối người quyên góp, tình nguyện viên và người cần giúp đỡ.\n\n🛍️ [Shop](/shop.php) | 💝 [Donate](/donate.php) | 📣 [Campaigns](/campaigns.php) | 🤝 [Volunteer](/volunteer.php)"], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['hello', 'hi there', 'hey', 'xin chào', 'xin chao', 'chào', 'chao'])) {
                    echo json_encode(['success' => true, 'reply' => "Xin chào! 👋 Mình là trợ lý AI của **GoodWill Việt Nam**.\n\n💝 [Quyên góp](/donate.php) | 🛍️ [Shop](/shop.php) | 📣 [Campaigns](/campaigns.php) | 🤝 [Volunteer](/volunteer.php)\n\nBạn cần hỗ trợ gì không?"], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['login', 'sign in', 'đăng nhập', 'dang nhap'])) {
                    echo json_encode(['success' => true, 'reply' => "Đăng nhập tại [đây](/login.php). Quên mật khẩu? [Forgot Password](/forgot-password.php)"], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['register', 'sign up', 'đăng ký', 'dang ky', 'tạo tài khoản', 'tao tai khoan'])) {
                    echo json_encode(['success' => true, 'reply' => "Tạo tài khoản miễn phí tại [Register](/register.php)."], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['faq', 'frequently asked', 'câu hỏi thường gặp', 'cau hoi thuong gap'])) {
                    echo json_encode(['success' => true, 'reply' => "Xem câu hỏi thường gặp tại [FAQ](/faq.php)."], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['recruitment', 'tuyển dụng', 'tuyen dung', 'ứng tuyển', 'ung tuyen', 'việc làm', 'viec lam'])) {
                    echo json_encode(['success' => true, 'reply' => "GoodWill đang tuyển dụng! Xem và nộp đơn tại [Tuyển dụng](/recruitment.php)."], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['cart', 'giỏ hàng', 'gio hang', 'checkout', 'thanh toán', 'thanh toan'])) {
                    echo json_encode(['success' => true, 'reply' => "Xem [Giỏ hàng](/cart.php) và tiến hành [Thanh toán](/checkout.php)."], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                if (contains_any_keyword($normalizedMessage, ['contact', 'liên hệ', 'lien he', 'support team'])) {
                    echo json_encode(['success' => true, 'reply' => "📧 info@goodwillvietnam.com | 📞 +84 123 456 789\nHoặc gửi [Phản hồi](/feedback.php)."], JSON_UNESCAPED_UNICODE);
                    exit();
                }

                echo json_encode([
                    'success' => true,
                    'reply' => 'Hiện chatbot AI đang quá tải tạm thời. Bạn thử lại sau ít phút giúp mình nhé.'
                ], JSON_UNESCAPED_UNICODE);
                exit();
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }

    echo json_encode([
        'success' => false,
        'message' => $lastError !== '' ? $lastError : 'Khong the xu ly yeu cau voi Gemini luc nay.'
    ]);
    exit();
}

$reply = '';
if (!empty($responseData['candidates'][0]['content']['parts']) && is_array($responseData['candidates'][0]['content']['parts'])) {
    foreach ($responseData['candidates'][0]['content']['parts'] as $part) {
        if (isset($part['text'])) {
            $reply .= (string) $part['text'];
        }
    }
}

$reply = trim($reply);
if ($reply === '') {
    $reply = 'Xin loi, minh chua the tra loi luc nay. Ban thu lai giup minh nhe.';
}

$history[] = [
    'role' => 'model',
    'parts' => [
        ['text' => $reply]
    ]
];

if (count($history) > 20) {
    $history = array_slice($history, -20);
}

$_SESSION['gemini_chat_history'] = $history;

echo json_encode([
    'success' => true,
    'reply' => $reply
], JSON_UNESCAPED_UNICODE);
