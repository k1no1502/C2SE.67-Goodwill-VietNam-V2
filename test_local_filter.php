<?php
mb_internal_encoding('UTF-8');

function checkToxicTextLocal($text) {
    if (trim($text) === '') return null;
    $normalized = mb_strtolower($text, 'UTF-8');
    $cleaned = preg_replace('/(?<=\pL)[.\-*_@#!\s]+(?=\pL)/u', '', $normalized);

    $bannedWords = [
        'cặc','cac','cặk','cak','kặc','kak',
        'lồn','lon','loz','l0n',
        'đụ','du má','đụ má','địt','dit','đít','đĩ',
        'đéo','deo','đếch','dech',
        'cứt','cut',
        'buồi','buoi','dái',
        'đm','đkm','dkm','dmm','đmm','dcm','đcm',
        'vl','vcl','vkl','vãi','vãi lồn','vãi cặc',
        'fuck','shit','bitch','dick','pussy',
        'con cặc','con cac','con kặc',
        'con lồn','con lon',
        'đồ chó','thằng chó','con chó',
        'mẹ mày','má mày',
        'ngu','óc chó',
        'thằng ngu','con ngu','đồ ngu',
        'chịch','chich','porn',
    ];

    foreach ($bannedWords as $word) {
        $wl = mb_strtolower($word, 'UTF-8');
        if (mb_strpos($normalized, $wl) !== false) return $word;
        if (mb_strpos($cleaned, $wl) !== false) return $word;
    }

    $patterns = [
        '/c[.\-*\s]*[aăặ][.\-*\s]*[ck]/ui',
        '/l[.\-*\s]*[oồô][.\-*\s]*n/ui',
        '/đ[.\-*\s]*[iị][.\-*\s]*t/ui',
        '/b[.\-*\s]*u[.\-*\s]*[oồ][.\-*\s]*i/ui',
        '/v[.\-*\s]*[ck][.\-*\s]*l/ui',
    ];

    foreach ($patterns as $p) {
        if (preg_match($p, $text)) return '(regex)';
    }

    return null;
}

$tests = [
    // Gốc
    'CON CẶC',
    'địt mẹ',
    'fuck you',
    // Bypass bằng . * -
    'c.ặ.c',
    'đ.m',
    'con c*ặ*c',
    // Bypass bằng @ $ # % & ^ ! ~
    'c@c',
    'c$c',
    'c#c',
    'c%c',
    'c&c',
    'l@n',
    'l$n',
    'đ@t',
    'đ$t',
    'v@c@l',
    // Bypass bằng số (leetspeak)
    'l0n',
    'd1t',
    'c4c',
    'bu01',
    '$hit',
    'f*ck',
    'b1tch',
    // Từ mới bổ sung
    'đĩ điếm',
    'chó đẻ',
    'khốn nạn',
    'motherfucker',
    'nigga',
    'con đĩ',
    'mặt lồn',
    'cái cặc',
    'phuck',
    // An toàn - PHẢI cho qua
    'Áo đẹp quá',
    'Quần áo tốt',
    'Áo khoác mùa đông',
    'Sách hay',
    'Laptop Dell',
    'Bàn học gỗ',
];

foreach ($tests as $t) {
    $r = checkToxicTextLocal($t);
    echo $t . ' => ' . ($r ? "BLOCKED ($r)" : 'OK') . PHP_EOL;
}
