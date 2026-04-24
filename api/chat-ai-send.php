<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$message = trim($_POST['message'] ?? '');
if ($message === '') {
    echo json_encode([
        'success' => true,
        'reply' => 'Vui lòng liên hệ đến số hotline: 0964821707 để được hỗ trợ thêm'
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

function remove_vietnamese_accents($str) {
    if (!$str) return '';
    $str = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u', 'a', $str);
    $str = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u', 'e', $str);
    $str = preg_replace('/(ì|í|ị|ỉ|ĩ)/u', 'i', $str);
    $str = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u', 'o', $str);
    $str = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u', 'u', $str);
    $str = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/u', 'y', $str);
    $str = preg_replace('/(đ)/u', 'd', $str);
    $str = preg_replace('/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/u', 'A', $str);
    $str = preg_replace('/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/u', 'E', $str);
    $str = preg_replace('/(Ì|Í|Ị|Ỉ|Ĩ)/u', 'I', $str);
    $str = preg_replace('/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/u', 'O', $str);
    $str = preg_replace('/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/u', 'U', $str);
    $str = preg_replace('/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/u', 'Y', $str);
    $str = preg_replace('/(Đ)/u', 'D', $str);
    return $str;
}

function normalize_vietnamese_string($str) {
    if (!class_exists('Normalizer')) {
        return $str;
    }
    return Normalizer::normalize($str, Normalizer::FORM_C);
}

function contains_any_keyword_strong($msg, $keywords) {
    if (!$msg || !is_array($keywords)) return false;
    
    // Normalize into composed form (NFC) so that composed vs decomposed issues are resolved
    if (class_exists('Normalizer')) {
        $msg = Normalizer::normalize($msg, Normalizer::FORM_C);
    }
    
    $msgLower = mb_strtolower($msg, 'UTF-8');
    $msgNoAccent = remove_vietnamese_accents($msgLower);
    
    foreach ($keywords as $kw) {
        if (class_exists('Normalizer')) {
            $kw = Normalizer::normalize($kw, Normalizer::FORM_C);
        }
        $kwLower = mb_strtolower($kw, 'UTF-8');
        $kwNoAccent = remove_vietnamese_accents($kwLower);
        
        if (mb_strpos($msgLower, $kwLower, 0, 'UTF-8') !== false || mb_strpos($msgNoAccent, $kwNoAccent, 0, 'UTF-8') !== false) {
            return true;
        }
    }
    return false;
}

$normalizedMessage = mb_strtolower($message, 'UTF-8');

// ------ CÂU HỎI THƯỜNG GẶP (SHORTCUTS) ------

$isAskingRules = contains_any_keyword_strong($normalizedMessage, ['quy định', 'quy dinh', 'các bước', 'cac buoc', 'quy trình', 'quy trinh', 'làm sao', 'lam sao', 'hướng dẫn', 'huong dan', 'như thế nào', 'nhu the nao']);
$isAboutDonation = contains_any_keyword_strong($normalizedMessage, ['quyên góp', 'quyen gop', 'ủng hộ', 'ung ho', 'donate']);

// 1. Quy định / Các bước quyên góp
if ($isAskingRules && $isAboutDonation) {
    echo json_encode([
        'success' => true,
        'reply' => "**Quy trình quyên góp**\n1. Điền thông tin vật phẩm hoặc số tiền muốn ủng hộ\n2. Xác nhận thông tin nhận hàng hoặc phương thức thanh toán\n3. Theo dõi trạng thái xử lý trong tài khoản của bạn\n\n**Lưu ý nhanh**\n* Ảnh rõ ràng giúp duyệt nhanh hơn.\n* Nên nhập mô tả tình trạng chi tiết.\n* Bạn có thể nhập danh sách bằng Excel/CSV.\n\n🚨 **Quy định khi quyên góp**\n* ❌ Không sử dụng từ ngữ thô tục, phản cảm hoặc nội dung 18+.\n* ❌ Không upload hình ảnh bậy bạ, nhạy cảm hoặc không phù hợp.\n* 📍 Thông tin địa chỉ nhận hàng phải chính xác, đầy đủ.\n* 🛡️ Nội dung được kiểm duyệt tự động bởi AI."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// 2. Tôi muốn quyên góp
if (contains_any_keyword_strong($normalizedMessage, ['tôi muốn quyên góp', 'toi muon quyen gop', 'tôi muốn ủng hộ', 'toi muon ung ho', 'quyên góp ở đâu', 'quyen gop o dau'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Nhấn vào [đây](/donate.php) để đến tới trang quyên góp nhé! 🎁"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Mua sắm (shop)
if (contains_any_keyword_strong($normalizedMessage, ['mua sắm', 'mua sam', 'mua hàng', 'mua hang', 'shop', 'cửa hàng'])) {
    if (contains_any_keyword_strong($normalizedMessage, ['cách', 'hướng dẫn', 'bước', 'làm sao'])) {
        echo json_encode([
            'success' => true,
            'reply' => "Dưới đây là các bước mua sắm trên shop: 🛒\n\n1. Truy cập trang [Shop](/shop.php)\n2. Duyệt danh sách sản phẩm hoặc sử dụng tìm kiếm\n3. Chọn sản phẩm bạn muốn mua\n4. Xem chi tiết sản phẩm và giá cả\n5. Nhấn Thêm vào giỏ hàng\n6. Tiến hành thanh toán\n7. Hoàn tất đơn hàng\n\nBạn có thể nhấn vào [đường dẫn này](/shop.php) để bắt đầu mua sắm ngay nhé! ✨"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    echo json_encode([
        'success' => true,
        'reply' => "Bạn có thể nhấn vào [đường dẫn này](/shop.php) để vào trang Shop mua sắm nhé! 🛍️"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Tạo chiến dịch
if (contains_any_keyword_strong($normalizedMessage, ['tạo chiến dịch', 'tao chien dich', 'chiến dịch mới'])) {
    if (contains_any_keyword_strong($normalizedMessage, ['cách', 'hướng dẫn', 'bước', 'làm sao'])) {
        echo json_encode([
            'success' => true,
            'reply' => "Dưới đây là các bước tạo chiến dịch: 📢\n\n1. Truy cập trang [tạo chiến dịch](/create-campaign.php)\n2. Điền thông tin cơ bản về chiến dịch (tiêu đề, mô tả)\n3. Tải lên hình ảnh đại diện cho chiến dịch\n4. Đặt mục tiêu tài chính (nếu có)\n5. Chọn danh mục phù hợp\n6. Nhấn nút Tạo để hoàn tất\n\nBạn có thể nhấn vào [đường dẫn này](/create-campaign.php) để tạo chiến dịch ngay nhé! 🚀"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    echo json_encode([
        'success' => true,
        'reply' => "Bạn có thể nhấn vào [đường dẫn này](/create-campaign.php) để dẫn đến trang tạo chiến dịch nha! 🎯"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Tham gia Chiến dịch (Quyên góp chiến dịch, Tình nguyện viên chiến dịch)
if (contains_any_keyword_strong($normalizedMessage, ['chiến dịch', 'chien dich'])) {
    if (contains_any_keyword_strong($normalizedMessage, ['tình nguyện', 'tinh nguyen', 'đăng kí', 'đăng ký', 'dang ki', 'dang ky', 'đanwg kí', 'danwg ki'])) {
        echo json_encode([
            'success' => true,
            'reply' => "Để đăng ký tình nguyện viên tham gia chiến dịch, bạn có thể nhấn vào [đường dẫn này](/campaigns.php) để xem các chiến dịch đang diễn ra hoặc vào mục [Tình nguyện](/volunteer.php) nhé! 🤝"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    if (contains_any_keyword_strong($normalizedMessage, ['quyên góp', 'quyen gop', 'ủng hộ', 'ung ho', 'quiyeen góp', 'quiyeen gop'])) {
        echo json_encode([
            'success' => true,
            'reply' => "Để quyên góp cho một chiến dịch cụ thể, bạn hãy nhấn vào [đường dẫn này](/campaigns.php) để chọn chiến dịch mình muốn đồng hành nhé! ❤️"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// Tình nguyện viên chung
if (contains_any_keyword_strong($normalizedMessage, ['tình nguyện viên', 'tinh nguyen vien', 'tham gia tình nguyện', 'đăng kí tình nguyện', 'đanwg kí'])) {
    if (contains_any_keyword_strong($normalizedMessage, ['cách', 'hướng dẫn', 'bước', 'làm sao'])) {
        echo json_encode([
            'success' => true,
            'reply' => "Dưới đây là các bước tham gia tình nguyện viên: 🙋‍♂️🙋‍♀️\n\n1. Truy cập trang [tình nguyện viên](/volunteer.php)\n2. Xem danh sách các vị trí tình nguyện hiện có\n3. Chọn vị trí bạn quan tâm\n4. Điền thông tin cá nhân và lý do tham gia\n5. Gửi đơn xin tham gia\n6. Chờ xác nhận từ admin\n\nBạn có thể nhấn vào [đường dẫn này](/volunteer.php) để đăng ký tham gia ngay nhé! ✨"
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
    echo json_encode([
        'success' => true,
        'reply' => "Bạn có thể nhấn vào [đường dẫn này](/volunteer.php) để vào trang tham gia tình nguyện viên nha! 🌟"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// Lịch sử
if (contains_any_keyword_strong($normalizedMessage, ['lịch sử đơn hàng', 'đơn hàng của tôi', 'xem đơn hàng', 'my orders', 'đonw hàng', 'donw hang'])) {
    echo json_encode(['success' => true, 'reply' => "Bạn có thể nhấn vào [đường dẫn này](/my-orders.php) để xem lịch sử đơn hàng của bạn nhé! 📦"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['lịch sử quyên góp', 'quyên góp của tôi', 'my donations'])) {
    echo json_encode(['success' => true, 'reply' => "Bạn có thể nhấn vào [đường dẫn này](/my-donations.php) để xem lịch sử quyên góp của bạn nha! 💝"], JSON_UNESCAPED_UNICODE);
    exit();
}

// Account options
if (contains_any_keyword_strong($normalizedMessage, ['hồ sơ của tôi', 'xem hồ sơ', 'ho so', 'hồ sơ', 'profile', 'tài khoản'])) {
    echo json_encode(['success' => true, 'reply' => "Bạn có thể nhấn vào [đường dẫn này](/profile.php) để xem hồ sơ của bạn nhé! 👤"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['phản hồi', 'feedback', 'đánh giá'])) {
    echo json_encode(['success' => true, 'reply' => "Bạn có thể nhấn vào [đường dẫn này](/feedback.php) để vào trang phản hồi nha! 💬"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['đổi mật khẩu', 'change password'])) {
    echo json_encode(['success' => true, 'reply' => "Bạn có thể nhấn vào [đường dẫn này](/change-password.php) để đổi mật khẩu bảo mật tài khoản nhé! 🔒"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['liên hệ', 'contact', 'hỗ trợ kỹ thuật'])) {
    echo json_encode(['success' => true, 'reply' => "Bạn có thể liên hệ hỗ trợ qua:\n\n📧 Email: info@goodwillvietnam.com\n📞 Hotline: 0964821707\n💬 Chat với nhân viên ngay trong hộp chat này\n📝 Gửi phản hồi: [Feedback](/feedback.php)"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['faq', 'câu hỏi thường gặp'])) {
    echo json_encode(['success' => true, 'reply' => "Xem các câu hỏi thường gặp tại trang [FAQ](/faq.php) nhé! ❓"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['tuyển dụng', 'recruitment', 'việc làm'])) {
    echo json_encode(['success' => true, 'reply' => "GoodWill Việt Nam đang tuyển dụng! 🎉\n\nXem các vị trí và nộp đơn tại [Tuyển dụng](/recruitment.php)."], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['nhiệm vụ của tôi', 'my tasks'])) {
    echo json_encode(['success' => true, 'reply' => "Xem danh sách nhiệm vụ của bạn tại [Nhiệm vụ của tôi](/my-tasks.php) nha! 📋"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['thông báo', 'notification'])) {
    echo json_encode(['success' => true, 'reply' => "Xem thông báo của bạn tại [Thông báo](/notifications.php) nhé! 🔔"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['giỏ hàng', 'cart', 'thanh toán'])) {
    echo json_encode(['success' => true, 'reply' => "Xem giỏ hàng tại [Giỏ hàng](/cart.php). Khi sẵn sàng, hãy tiến hành [Thanh toán](/checkout.php) nhé! 💳"], JSON_UNESCAPED_UNICODE);
    exit();
}
if (contains_any_keyword_strong($normalizedMessage, ['quyên góp', 'quyen gop', 'ủng hộ', 'donate'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Bạn có thể nhấn vào [đường dẫn này](/donate.php) để dẫn đến trang quyên góp nhé! 🎁"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// CÂU HỎI KIẾN THỨC CHUNG
if (contains_any_keyword_strong($normalizedMessage, ['chiến dịch là gì', 'chien dich la gi', 'thế nào là chiến dịch', 'the nao la chien dich', 'khái niệm chiến dịch', 'ý nghĩa chiến dịch'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Chiến dịch (Campaign) trên GoodWill là một dự án thiện nguyện do cá nhân hoặc tổ chức khởi tạo nhằm kêu gọi sự ủng hộ về vật phẩm, tài chính và sự góp sức của tình nguyện viên cho một mục đích nhân đạo cụ thể (VD: Giúp đỡ trẻ em vùng cao, Hỗ trợ đồng bào lũ lụt).\n\nBạn có thể vào mục [Chiến dịch](/campaigns.php) để tìm hiểu và tham gia nhé! 🌟"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['mục đích', 'muc dich', 'mục đính', 'muc dinh', 'trang web này để làm gì', 'website này là gì', 'website nay la gi', 'trang web này làm gì', 'goodwill là gì', 'goodwill la gi'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Mục đích chính của trang web **GoodWill Việt Nam** là tạo ra một nền tảng kết nối những người có tấm lòng vàng với những người đang có hoàn cảnh khó khăn.\n\nTại đây, bạn có thể:\n1. 🎁 Tham gia quyên góp đồ dùng, quần áo cũ hoặc tiền mặt.\n2. 🛒 Nhận hoặc mua sắm vật phẩm tại Shop Miễn phí / Giá rẻ.\n3. 🤝 Trở thành tình nguyện viên cho các dự án cộng đồng.\n4. 📢 Khởi tạo chiến dịch kêu gọi mọi người cùng chung tay giúp đỡ."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['quyên góp là gì', 'quyen gop la gi', 'thế nào là quyên góp', 'the nao la quyen gop', 'ủng hộ là gì'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Quyên góp là hành động trao tặng tự nguyện tài sản (quần áo, sách vở, thiết bị, nhu yếu phẩm...) hoặc tiền bạc nhằm san sẻ, giúp đỡ những người có hoàn cảnh khó khăn hoặc ủng hộ cho các chiến dịch nhân đạo.\n\nMọi sự đóng góp của bạn dù lớn hay nhỏ đều mang ý nghĩa rất lớn đối với cộng đồng. Nhấn vào [đây](/donate.php) nếu bạn muốn bắt đầu quyên góp nhé!"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['điều kiện kinh tế', 'dieu kien kinh te', 'người giàu có được mua', 'nguoi giau co duoc mua', 'có tiền có được mua', 'co tien co duoc mua'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Tất nhiên là **ĐƯỢC** nhé! 🌟\n\nShop GoodWill luôn mở cửa chào đón tất cả mọi người. Nếu bạn là người có điều kiện kinh tế, việc bạn mua các sản phẩm thanh lý tại gian hàng sẽ trực tiếp đóng góp vào quỹ hoạt động của GoodWill. Toàn bộ doanh thu này sẽ được tái đầu tư để duy trì hệ thống và hỗ trợ thêm nhiều người có hoàn cảnh khó khăn hơn nữa.\n\nHành động mua sắm của bạn cũng chính là một hình thức quyên góp vô cùng ý nghĩa!"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['ngọn lửa nào', 'ngon lua nao', 'động lực nào', 'dong luc nao', 'lý do duy trì', 'ly do duy tri', 'tại sao tồn tại'])) {
    echo json_encode([
        'success' => true,
        'reply' => "\"Ngọn lửa\" lớn nhất giúp GoodWill Việt Nam cháy mãi và duy trì chính là **Tình người và Sự tử tế**. 🔥\n\nĐó là nụ cười của em nhỏ vùng cao khi nhận được chiếc áo ấm, là niềm hạnh phúc của người lao động nghèo khi có được món đồ thiết yếu, và là sự nhiệt huyết không ngừng nghỉ của hàng ngàn người quyên góp cũng như các tình nguyện viên.\n\nChính khát khao thu hẹp khoảng cách xã hội, giảm thiểu rác thải và lan tỏa yêu thương đã tiếp thêm sức mạnh để dự án này không ngừng phát triển."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['lợi ích của web', 'loi ich cua web', 'người khó khăn ra sao', 'nguoi kho khan ra sao', 'giúp được gì cho người nghèo', 'giup duoc gi cho nguoi ngheo'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Đối với những người có hoàn cảnh khó khăn, GoodWill mang đến những lợi ích vô cùng thiết thực:\n\n1. 🛒 **Tiếp cận nhu yếu phẩm miễn phí**: Tại Shop, họ có thể tìm thấy quần áo, sách vở, đồ gia dụng mà không phải lo lắng về rào cản tài chính.\n2. ❤️ **Nhận hỗ trợ từ Chiến dịch**: Thông qua các dự án cộng đồng, họ được giúp đỡ tận nơi bằng vật phẩm và tài chính.\n3. 🌟 **Bảo vệ sự tôn nghiêm**: Mô hình 'Gian hàng 0 đồng' giúp họ tự do lựa chọn món đồ mình thực sự cần như một người mua hàng bình thường, mang lại cảm giác được tôn trọng."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['chiến dịch hoạt động', 'chien dich hoat dong', 'cách thức hoạt động của chiến dịch', 'cach thuc hoat dong cua chien dich'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Một chiến dịch trên GoodWill hoạt động theo quy trình 4 bước rất minh bạch:\n\n1. 📝 **Khởi tạo**: Các cá nhân/tổ chức tạo chiến dịch, thiết lập mục tiêu số lượng vật phẩm hoặc số tiền cần kêu gọi.\n2. 📣 **Lan tỏa & Quyên góp**: Cộng đồng vào xem, đóng góp vật phẩm/tiền bạc và đăng ký làm Tình nguyện viên.\n3. 🚚 **Phân phối**: Tình nguyện viên sẽ nhận đồ từ hệ thống và trao tận tay cho những người yếu thế.\n4. ✅ **Kết thúc & Chia sẻ**: Khi chiến dịch hoàn thành, các vật phẩm còn dư sẽ tự động được chuyển lên **Shop** để tiếp tục trao đi, đảm bảo không có sự lãng phí nào!"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['đồ cũ có an toàn', 'do cu co an toan', 'chất lượng đồ', 'chat luong do', 'đồ cũ có tốt', 'do cu co tot'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Bạn hoàn toàn có thể yên tâm! 🛡️\n\nMọi vật phẩm quyên góp trên GoodWill đều trải qua quá trình kiểm duyệt khắt khe từ đội ngũ Admin và công nghệ AI. Những món đồ quá cũ nát, hư hỏng hoặc không còn giá trị sử dụng sẽ bị hệ thống loại bỏ.\n\nĐồng thời, chúng tôi luôn kêu gọi người tặng giặt giũ, làm sạch đồ dùng trước khi trao đi như một cách thể hiện sự tôn trọng sâu sắc với người nhận."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['ai đứng sau', 'ai dung sau', 'người tạo ra', 'nguoi tao ra', 'đội ngũ sáng lập', 'doi ngu sang lap'])) {
    echo json_encode([
        'success' => true,
        'reply' => "GoodWill Việt Nam không thuộc về riêng một cá nhân nào, mà thuộc về **Cộng đồng**. 🌍\n\nĐứng sau dự án là những nhà phát triển tâm huyết, sự đóng góp của các mạnh thường quân và hàng ngàn tình nguyện viên không quản ngại khó khăn.\n\nChính bạn - khi đang đọc những dòng này và tham gia vào nền tảng - cũng là một mảnh ghép vô cùng quan trọng tạo nên sự sống còn và thành công của GoodWill!"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['lợi nhuận', 'loi nhuan', 'doanh thu', 'tiền thu được', 'tien thu duoc', 'làm từ thiện có lãi', 'khoản nào', 'khoan nao'])) {
    echo json_encode([
        'success' => true,
        'reply' => "GoodWill là dự án **phi lợi nhuận**. Toàn bộ doanh thu có được (từ việc những người có điều kiện mua sắm vật phẩm trên Shop) đều được dùng để tái đầu tư vào 3 khoản chính:\n\n1. 🔧 **Chi phí vận hành**: Trả phí server, duy trì hệ thống AI kiểm duyệt, đóng gói và kho bãi.\n2. 🚚 **Hỗ trợ vận chuyển**: Phụ cấp một phần chi phí giao hàng cho người nhận nghèo và tình nguyện viên.\n3. ❤️ **Quỹ Dòng chảy Tình thương**: Trực tiếp tài trợ cho các chiến dịch thiện nguyện cấp bách (cứu trợ thiên tai, y tế khẩn cấp).\n\nChúng tôi luôn công khai minh bạch báo cáo tài chính để mọi người cùng giám sát!"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['sợ bị lừa', 'so bi lua', 'minh bạch', 'minh bach', 'lừa đảo', 'lua dao', 'tin tưởng', 'tin tuong'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Sự minh bạch là tôn chỉ sống còn của GoodWill! 🛡️\n\nMọi quy trình từ lúc bạn quyên góp vật phẩm/tiền bạc cho đến khi người nhận cầm trên tay đều được số hóa và ghi nhận trên hệ thống. \nBạn hoàn toàn có thể theo dõi **Lịch sử quyên góp** của mình, cập nhật trạng thái đơn hàng theo thời gian thực và xem báo cáo tài chính công khai của từng Chiến dịch. Tại GoodWill, mỗi sự cho đi đều được đặt đúng chỗ và tôn trọng tuyệt đối!"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['người nhận có khó khăn', 'nguoi nhan co kho khan', 'xác minh người nhận', 'xac minh nguoi nhan', 'ai là người nhận'])) {
    echo json_encode([
        'success' => true,
        'reply' => "GoodWill sử dụng hệ thống đánh giá đa lớp để đảm bảo vật phẩm đến đúng tay người cần:\n\n1. 🤖 **Công nghệ AI**: Phân tích lịch sử hoạt động, tần suất nhận quà để phát hiện các hành vi gom hàng trục lợi.\n2. 👥 **Tình nguyện viên**: Những người sâu sát với cộng đồng sẽ trực tiếp hỗ trợ, xác minh và trao tận tay quà tặng.\n3. 🤝 **Đối tác uy tín**: Chúng tôi phối hợp cùng các tổ chức xã hội, nhà trường và chính quyền khu vực để lập danh sách những hoàn cảnh thực sự cần giúp đỡ."
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (contains_any_keyword_strong($normalizedMessage, ['quần áo rách', 'quan ao rach', 'đồ hỏng', 'do hong', 'rác thải', 'rac thai', 'đồ không dùng được', 'do khong dung duoc'])) {
    echo json_encode([
        'success' => true,
        'reply' => "Cảm ơn tấm lòng của bạn, nhưng GoodWill **không tiếp nhận** quần áo rách nát hoặc đồ vật đã hư hỏng không thể sử dụng. 💔\n\nChúng ta trao đi không chỉ là vật chất mà còn là **sự tôn trọng**. Xin hãy chỉ quyên góp những món đồ còn giá trị sử dụng, được giặt giũ và vệ sinh sạch sẽ. Đối với những vật phẩm đã hỏng, bạn hãy phân loại chúng thành rác thải tái chế để bảo vệ môi trường nhé!"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

// ------ GỌI GEMINI API ------
$config = require __DIR__ . '/../config/google.php';
$apiKey = trim((string)($config['gemini_api_key'] ?? ''));

if ($apiKey === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Hệ thống chưa cấu hình Gemini API key.'
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
                'text' => 'Ban la tro ly GoodWill Viet Nam. Tra loi lich su, ngan gon, de hieu, uu tien tieng Viet. Neu can, hoi them thong tin de ho tro dung nhu cau nguoi dung. (Giao tiep theo chu de Quyen Gop, Tinh nguyen vien, Mua hang thiep)'
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

    if (stripos($apiError, 'not found') !== false || stripos($apiError, 'not supported') !== false) {
        continue;
    }

    break;
}

if (!is_array($responseData)) {
    echo json_encode([
        'success' => true,
        'reply' => 'Hiện chatbot AI đang quá tải tạm thời. Vui lòng liên hệ đến số hotline: 0964821707 để được hỗ trợ thêm.'
    ], JSON_UNESCAPED_UNICODE);
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
    $reply = 'Vui lòng liên hệ đến số hotline: 0964821707 để được hỗ trợ thêm';
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
