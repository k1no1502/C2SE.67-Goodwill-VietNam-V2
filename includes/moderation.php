<?php
/**
 * Kiểm tra ảnh NSFW bằng Qwen 3.5 thông qua service nội bộ (Node.js)
 * Trả về true nếu ảnh vi phạm (nude, sex, porn, lộ ngực, lộ núm...)
 */
// Trả về mảng ['violate' => bool, 'reason' => string]
// ==========================================
// 1. Kiểm duyệt bằng HUGGING FACE INFERENCE API
// Sử dụng mô hình phân loại NSFW (porn, hentai, sexy, neutral)
// ==========================================
function checkNsfwImageHuggingFace(string $imagePath): array {
    $apiUrl = 'https://router.huggingface.co/hf-inference/models/Falconsai/nsfw_image_detection';
    $apiKey = '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'; 

    if (!file_exists($imagePath)) return ['violate' => false, 'reason' => ''];
    $imageData = file_get_contents($imagePath);

    // Thử gọi tối đa 3 lần nếu Model đang loading (HTTP 503)
    $maxTries = 3;
    $httpCode = 0;
    $response = '';

    for ($i = 0; $i < $maxTries; $i++) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $apiKey,
            "Content-Type: application/octet-stream"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 503) {
            sleep(4); // Đợi 4 giây cho model nạp xong
            continue;
        }
        break; // Thoát nếu trả về 200 (Thành công) hoặc lỗi khác
    }

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if (is_array($result) && !empty($result)) {
            $nsfwScore = 0;
            foreach ($result as $item) {
                $label = strtolower($item['label'] ?? '');
                $score = (float)($item['score'] ?? 0);
                if (in_array($label, ['nsfw', 'porn', 'hentai', 'sexy'])) {
                    $nsfwScore += $score;
                }
            }
            if ($nsfwScore > 0.4) {
                return ['violate' => true, 'reason' => 'Hệ thống AI (Hugging Face) phát hiện ảnh chứa nội dung khiêu dâm/gợi dục (porn, hentai, sexy).'];
            }
        }
        return ['violate' => false, 'reason' => ''];
    }

    // NẾU HUGGING FACE BÁO LỖI (400, 413, 404, 500, v.v.)
    // Không bao giờ cho phép ảnh lọt qua nếu AI chưa kịp quét!
    $errorMsg = 'Hệ thống AI từ chối phân tích. ';
    if ($httpCode === 413) $errorMsg .= '(Ảnh quá nặng, vui lòng giảm dung lượng)';
    elseif ($httpCode === 503) $errorMsg .= '(Máy chủ AI đang quá tải, vui lòng thử lại)';
    else {
        // Lấy error message từ HF
        $resObj = json_decode($response, true);
        if (isset($resObj['error'])) $errorMsg .= '(' . $resObj['error'] . ')';
        else $errorMsg .= '(HTTP ' . $httpCode . ')';
    }
    return ['violate' => true, 'reason' => $errorMsg];
}

// ==========================================
// 2. Kiểm duyệt bằng service nội bộ (Node.js) cũ
// ==========================================
function checkNsfwImageQwen(string $imagePath): array {
    $serviceUrl = 'http://localhost:3001/check-file';
    if (!file_exists($imagePath)) return ['violate' => false, 'reason' => ''];
    $cfile = new CURLFile($imagePath);
    $postFields = ['image' => $cfile];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $serviceUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || !$response) return ['violate' => false, 'reason' => ''];
    $data = json_decode($response, true);
    if (isset($data['nsfw']) && $data['nsfw'] === true) {
        return ['violate' => true, 'reason' => $data['reason'] ?? 'Hình ảnh không phù hợp với quy định.'];
    }
    return ['violate' => false, 'reason' => ''];
}
/**
 * Content Moderation Functions
 * Kiểm duyệt nội dung: từ cấm (text), ảnh NSFW (image)
 * Dùng chung cho donate.php, create-campaign.php, recruitment.php, donate-to-campaign.php
 */

if (!function_exists('checkToxicTextLocal')) {

/**
 * Kiểm tra văn bản bằng danh sách từ cấm local (không cần API).
 * Bắt cả biến thể: viết hoa, chèn dấu chấm/sao/space, viết tắt, leetspeak.
 * Trả về từ vi phạm nếu tìm thấy, hoặc null nếu an toàn.
 */
function checkToxicTextLocal(string $text): ?string {
    if (trim($text) === '') return null;

    $normalized = mb_strtolower($text, 'UTF-8');

    // Leetspeak map
    $leetMap = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's',
        '7' => 't', '8' => 'b', '9' => 'g',
        '@' => 'a', '$' => 's', '!' => 'i', '+' => 't',
        '€' => 'e', '£' => 'l',
    ];
    $leetNormalized = strtr($normalized, $leetMap);

    $cleaned = preg_replace('/(?<=\pL)[^\pL]+(?=\pL)/u', '', $normalized);
    $leetCleaned = preg_replace('/(?<=\pL)[^\pL]+(?=\pL)/u', '', $leetNormalized);

    $bannedWords = [
        // === Từ tục tĩu tiếng Việt ===
        'cặc', 'cac', 'cặk', 'cak', 'kặc', 'kak', 'cu', 'kẹc', 'cẹc', 'ku',
        'lồn', 'lon', 'loz',
        'đụ', 'du má', 'đụ má', 'đụ mẹ', 'du me',
        'địt', 'dit', 'đít', 'đĩ', 'đĩ điếm', 'con đĩ',
        'đéo', 'deo', 'đếch', 'dech', 'đái', 'cứt',
        'buồi', 'buoi', 'dái',
        // === Chửi thề viết tắt VN ===
        'đm', 'đkm', 'dkm', 'dmm', 'đmm', 'dcm', 'đcm',
        'cdmm', 'cmm',
        'vl', 'vcl', 'vkl', 'vãi', 'vai lon', 'vãi lồn', 'vãi cặc',
        'clm', 'cl', 'conmemay', 'bj',
        // === Cụm từ tục tiếng Việt ===
        'con cặc', 'con cac', 'con kặc', 'con lồn', 'con lon',
        'chặt cu', 'chặt con cặc', 'bao quy đầu', 'đầu khấc',
        'cái lồn', 'cái cặc', 'mặt lồn', 'mặt cặc',
        'đồ chó', 'do cho', 'thằng chó', 'con chó', 'chó đẻ',
        'mẹ mày', 'me may', 'má mày', 'ma may', 'đ mày', 'đ m',
        'địt mẹ', 'dit me',
        'ngu', 'óc chó', 'oc cho',
        'thằng ngu', 'con ngu', 'đồ ngu',
        'thằng điên', 'con điên', 'đồ điên',
        'thằng khùng', 'thằng khốn', 'đồ khốn', 'khốn nạn',
        'đồ rác', 'đồ nát', 'chết mẹ',
        'chịch', 'chich', 'dâm dục', 'dam duc',
        'xe lỗ nhị', 'xe lỗ đít', 'xe lông bướm',
        'bứt lông dái', 'bứt lông cặc', 'thông lỗ đít',
        'bú', 'lít đỗ', 'tù ngay', 'tà bỏ chay',
        // === Tiếng Anh - Chửi thề ===
        'fuck', 'fck', 'fuk', 'phuck', 'phuk', 'fuq', 'f4ck',
        'shit', 'sht', 'bitch', 'dick', 'pussy', 'ass', 'asshole',
        'cunt', 'twat', 'cock', 'prick',
        'damn', 'dammit', 'goddamn', 'goddammit',
        'bastard', 'bollocks', 'bugger', 'crap',
        'wtf', 'stfu', 'gtfo', 'lmfao',
        'motherfucker', 'mfer', 'mofo',
        'son of a bitch', 'sob',
        'douchebag', 'douche', 'jackass', 'dipshit',
        'dumbass', 'fatass', 'smartass', 'badass', 'bullshit',
        // === Phân biệt / Kỳ thị ===
        'nigga', 'nigger', 'negro', 'chink', 'gook', 'spic',
        'kike', 'wetback', 'cracker', 'honky', 'gringo',
        'faggot', 'fag', 'dyke', 'tranny', 'homo',
        'retard', 'retarded', 'spaz', 'spastic',
        // === Xúc phạm phụ nữ ===
        'whore', 'slut', 'skank', 'hoe', 'thot', 'harlot', 'trollop',
        // === Gợi dục ===
        'porn', 'porno', 'xxx', 'hentai', 'nude', 'naked',
        'sexy', 'sexxy', 'boobs', 'boob', 'tits', 'titties',
        'dildo', 'vibrator', 'orgasm', 'blowjob', 'handjob',
        'cumshot', 'creampie', 'gangbang', 'threesome', 'orgy',
        'milf', 'gilf', 'anal', 'fellatio', 'cunnilingus',
        // === Bạo lực / Đe dọa ===
        'kill yourself', 'kys', 'go die', 'neck yourself',
    ];

    $variants = [$normalized, $cleaned, $leetNormalized, $leetCleaned];
    foreach ($bannedWords as $word) {
        $wordLower = mb_strtolower($word, 'UTF-8');
        foreach ($variants as $v) {
            if (mb_strpos($v, $wordLower) !== false) {
                return $word;
            }
        }
    }

    $sep = '[^\pL]*';
    $patterns = [
        // Tiếng Việt
        '/c'.$sep.'[aăặ]'.$sep.'[ck]/ui',
        '/l'.$sep.'[oồôò]'.$sep.'n/ui',
        '/đ'.$sep.'[iị]'.$sep.'t/ui',
        '/d'.$sep.'[iị]'.$sep.'t/ui',
        '/b'.$sep.'u'.$sep.'[oồò]'.$sep.'i/ui',
        '/v'.$sep.'[ck]'.$sep.'l/ui',
        '/d'.$sep.'[uụ]'.$sep.'m'.$sep.'[aá]/ui',
        '/c'.$sep.'[uứ]'.$sep.'t/ui',
        '/đ'.$sep.'[eéê]'.$sep.'o/ui',
        // Tiếng Anh
        '/f'.$sep.'[uü]'.$sep.'c'.$sep.'k/ui',
        '/s'.$sep.'h'.$sep.'[iì1!]'.$sep.'t/ui',
        '/b'.$sep.'[iì1!]'.$sep.'t'.$sep.'c'.$sep.'h/ui',
        '/d'.$sep.'[iì1!]'.$sep.'c'.$sep.'k/ui',
        '/p'.$sep.'u'.$sep.'s'.$sep.'s'.$sep.'y/ui',
        '/a'.$sep.'s'.$sep.'s/ui',
        '/c'.$sep.'u'.$sep.'n'.$sep.'t/ui',
        '/c'.$sep.'o'.$sep.'c'.$sep.'k/ui',
        '/w'.$sep.'h'.$sep.'o'.$sep.'r'.$sep.'e/ui',
        '/n'.$sep.'[i1!]'.$sep.'g'.$sep.'g'.$sep.'[ae]/ui',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return '(regex match)';
        }
    }

    return null;
}

/**
 * Kiểm tra văn bản qua Gemini AI
 */
function checkToxicTextGemini(string $text): array {
    $allText = trim($text);
    if ($allText === '') return ['violate' => false, 'reason' => ''];

    $apiKey = "AIzaSyCve4ReDIG4oWFZzA36sOp2o-I1nIq3Wxw";
    if (empty(trim($apiKey)) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') return ['violate' => false, 'reason' => ''];

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

    $prompt = "Bạn là hệ thống kiểm duyệt nội dung tiếng Việt chuyên nghiệp.\n\n"
            . "Kiểm tra văn bản sau có chứa nội dung VI PHẠM hay không:\n"
            . "- Từ tục tĩu, chửi thề, thô thiển (VD: đ.m, đ*t, cc, c.c, vcl, vl, dm, đkm, clm...)\n"
            . "- Biến thể né filter: viết tắt, chèn dấu chấm/dấu sao/space giữa các chữ cái\n"
            . "- Sỉ nhục, xúc phạm, phân biệt\n"
            . "- Nội dung 18+, khiêu dâm, gợi dục\n"
            . "- Lời lẽ đe dọa, kích động bạo lực\n\n"
            . "Trả lời ĐÚNG FORMAT (không giải thích thêm):\n"
            . "Dòng 1: SAFE hoặc TOXIC\n"
            . "Dòng 2: Nếu TOXIC, ghi lý do ngắn gọn bằng tiếng Việt (1 câu)\n\n"
            . "Văn bản cần kiểm tra:\n\"\"\"\n" . $allText . "\n\"\"\"";

    $postData = json_encode([
        "contents" => [["parts" => [["text" => $prompt]]]],
        "generationConfig" => ["maxOutputTokens" => 100, "temperature" => 0]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        // Cấp 1: Vi phạm cực kỳ nặng, API từ chối phản hồi
        if (isset($data['promptFeedback']['blockReason'])) {
            return ['violate' => true, 'reason' => 'Văn bản chứa nội dung vi phạm chính sách nghiêm trọng.'];
        }

        // Cấp 2: Bị chặn trong quá trình sinh giải đáp vì vi phạm chính sách (SAFETY)
        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
            return ['violate' => true, 'reason' => 'Văn bản chứa từ ngữ không an toàn.'];
        }

        // Cấp 3: Trả lời bình thường, có chứa từ TOXIC
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $answerFull = trim($data['candidates'][0]['content']['parts'][0]['text']);
            $lines = explode("\n", str_replace("\r", "", $answerFull));
            $answer = strtoupper(trim($lines[0]));
            if (strpos($answer, 'TOXIC') !== false || strpos($answer, 'YES') !== false || strpos($answer, 'CÓ') !== false) {
                $reason = isset($lines[1]) && trim($lines[1]) !== '' ? trim($lines[1]) : 'AI phát hiện từ ngữ vi phạm.';
                return ['violate' => true, 'reason' => $reason];
            }
        }
    }
    return ['violate' => false, 'reason' => ''];
}

/**
 * Kiểm tra ảnh NSFW bằng Google Gemini Vision
 */
function checkNsfwImageGemini(string $imagePath): array {
    $apiKey = "AIzaSyCve4ReDIG4oWFZzA36sOp2o-I1nIq3Wxw";
    if (empty(trim($apiKey)) || $apiKey === 'YOUR_GEMINI_API_KEY_HERE') return ['violate' => false, 'reason' => ''];

    $imageData = @file_get_contents($imagePath);
    if (!$imageData) return ['violate' => false, 'reason' => ''];

    $base64Image = base64_encode($imageData);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($imagePath);
    if (!in_array($mimeType, ['image/jpeg', 'image/png', 'image/gif', 'image/webp'])) {
        $mimeType = 'image/jpeg';
    }

    $apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

    $prompt = "Bạn là hệ thống kiểm duyệt ảnh chuyên nghiệp cho nền tảng từ thiện. Phân tích ảnh này và xác định ảnh có chứa nội dung KHÔNG PHÙ HỢP hay không.\n\n"
            . "Nội dung KHÔNG PHÙ HỢP bao gồm:\n"
            . "- Khỏa thân, bán khỏa thân, không mặc áo, cởi trần (kể cả nam giới cởi trần, ví dụ cầu thủ cởi trần), không mặc quần, lộ bộ phận sinh dục, ngực trần, mông\n"
            . "- Nội dung tình dục, BDSM, fetish, đồ chơi tình dục\n"
            . "- Hentai, anime 18+, deepfake porn, NSFW meme/sticker/GIF\n"
            . "- Gợi dục rõ ràng hoặc ngầm, upskirt, revenge porn\n"
            . "- Bạo lực đẫm máu, gore, tra tấn, ngược đãi động vật\n"
            . "- Vũ khí đe dọa, ma túy, chất cấm\n"
            . "- Nội dung thù hận, phân biệt chủng tộc, khủng bố\n"
            . "- Lạm dụng trẻ em (CSAM), tự gây hại, cổ súy tự tử\n"
            . "- Rượu bia, thuốc lá (quảng cáo rõ ràng)\n\n"
            . "Trả lời ĐÚNG FORMAT:\n"
            . "Dòng 1: SAFE hoặc NSFW\n"
            . "Dòng 2: Lý do ngắn gọn (1 câu tiếng Việt)";

    $postData = json_encode([
        "contents" => [[
            "parts" => [
                ["text" => $prompt],
                ["inline_data" => ["mime_type" => $mimeType, "data" => $base64Image]]
            ]
        ]],
        "generationConfig" => ["maxOutputTokens" => 100, "temperature" => 0]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 30
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        
        // Nếu API từ chối phản hồi ngay từ đầu do vi phạm chính sách nội dung (porn, v.v.)
        if (isset($data['promptFeedback']['blockReason'])) {
            return ['violate' => true, 'reason' => 'AI phát hiện hình ảnh chứa nội dung vi phạm chính sách nghiêm trọng.'];
        }

        // Nếu prompt qua được nhưng khi phân tích bị chặn (Finish Reason = SAFETY)
        if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
            return ['violate' => true, 'reason' => 'AI từ chối phân tích vì hình ảnh chứa nội dung không an toàn.'];
        }

        // Nếu API tự trả về câu trả lời phân loại
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $answerFull = trim($data['candidates'][0]['content']['parts'][0]['text']);
            $lines = explode("\n", str_replace("\r", "", $answerFull));
            $upperAnswer = strtoupper(trim($lines[0]));
            if (strpos($upperAnswer, 'NSFW') !== false || strpos($upperAnswer, 'KHÔNG PHÙ HỢP') !== false) {
                $reason = isset($lines[1]) && trim($lines[1]) !== '' ? trim($lines[1]) : 'Hệ thống AI phát hiện ảnh chứa nội dung không phù hợp.';
                return ['violate' => true, 'reason' => $reason];
            }
        }
        return ['violate' => false, 'reason' => ''];
    }

    // Nếu lỗi kết nối hoặc API lỗi (vd: hết hạn key, limit quota)
    $errorMsg = 'Hệ thống AI tạm thời không thể phân tích ảnh.';
    if ($response) {
        $resObj = json_decode($response, true);
        if (isset($resObj['error']['message'])) {
            $msg = strtolower($resObj['error']['message']);
            if (strpos($msg, 'expired') !== false || strpos($msg, 'invalid') !== false) {
                $errorMsg = 'Hệ thống AI chưa được cấu hình đúng (API Key không hợp lệ hoặc hết hạn). Không thể duyệt ảnh tự động.';
            } else {
                $errorMsg .= ' (' . $resObj['error']['message'] . ')';
            }
        } else {
            $errorMsg .= ' (HTTP ' . $httpCode . ')';
        }
    } else {
        $errorMsg .= ' (Không có phản hồi)';
    }
    return ['violate' => true, 'reason' => $errorMsg];
}

/**
 * Render HTML thông báo kiểm duyệt thất bại (styled giống donate.php)
 */
function renderModerationError(string $title, string $message): string {
    $escapedTitle = htmlspecialchars($title);
    $escapedMsg = htmlspecialchars($message);
    return <<<HTML
<div class="alert alert-danger border-danger" role="alert" style="background: linear-gradient(135deg, #fff5f5, #ffe0e0); border-left: 5px solid #dc3545 !important;">
    <div class="d-flex align-items-start gap-3">
        <div style="font-size: 2rem; line-height: 1;">
            <i class="bi bi-shield-x text-danger"></i>
        </div>
        <div>
            <h5 class="alert-heading text-danger fw-bold mb-1">
                <i class="bi bi-exclamation-octagon me-1"></i>{$escapedTitle}
            </h5>
            <p class="mb-2">{$escapedMsg}</p>
            <hr class="my-2">
            <p class="mb-0 small text-muted">
                <i class="bi bi-robot me-1"></i>Nội dung được kiểm duyệt tự động bởi AI.
                Vui lòng chỉnh sửa nội dung và thử lại, hoặc liên hệ hỗ trợ nếu bạn cho rằng đây là nhầm lẫn.
            </p>
        </div>
    </div>
</div>
HTML;
}

} // end function_exists check
