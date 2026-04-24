<?php
// Quick test for Gemini text moderation
$apiKey = "AIzaSyCve4ReDIG4oWFZzA36sOp2o-I1nIq3Wxw";
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

$testTexts = [
    "CON CẶC",
    "c.c",
    "đ.m mày",
    "Áo sơ mi trắng còn mới",
];

foreach ($testTexts as $text) {
    echo "=== Testing: '$text' ===\n";
    
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
            . "Văn bản cần kiểm tra:\n\"\"\"\n" . $text . "\n\"\"\"";

    $postData = json_encode([
        "contents" => [
            ["parts" => [["text" => $prompt]]]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 100,
            "temperature" => 0
        ]
    ]);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    echo "HTTP: $httpCode\n";
    if ($curlError) echo "CURL Error: $curlError\n";
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $answer = trim($data['candidates'][0]['content']['parts'][0]['text']);
            echo "Gemini answer: $answer\n";
            $upper = strtoupper($answer);
            if (strpos($upper, 'TOXIC') !== false) {
                echo "=> BLOCKED (toxic detected)\n";
            } else {
                echo "=> PASSED (safe)\n";
            }
        } else {
            echo "Unexpected response structure:\n";
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }
    } else {
        echo "Response: " . substr($response, 0, 500) . "\n";
    }
    echo "\n";
}
