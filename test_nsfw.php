
function checkNsfwImageHuggingFace($imagePath) {
    $apiToken = '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';
    $apiUrl = 'https://api-inference.huggingface.co/models/Qwen/Qwen3.5-35B-A3B';
    echo 'Fetching...';
    $imageData = @file_get_contents($imagePath);
    if (!$imageData) return 'no_file';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $imageData);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiToken,
        'Content-Type: application/octet-stream'
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return $httpCode . ' : ' . $response;
}
echo checkNsfwImageHuggingFace('temp.txt');

