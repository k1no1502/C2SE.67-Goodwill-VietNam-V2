<?php
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

$configPath = __DIR__ . '/config/social.php';
if (!file_exists($configPath)) {
    http_response_code(500);
    echo 'Missing social config.';
    exit();
}

$socialConfig = require $configPath;
$provider = $_GET['provider'] ?? '';
$provider = strtolower(trim($provider));

if (!isset($socialConfig[$provider])) {
    http_response_code(400);
    echo 'Invalid provider.';
    exit();
}

function httpRequest($url, $method = 'GET', $data = null, $headers = []) {
    $method = strtoupper($method);
    $defaultHeaders = ['Accept: application/json'];
    $headers = array_merge($defaultHeaders, $headers);

    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        if ($method !== 'GET' && $data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        if ($response === false) {
            return [null, $error ?: 'Request failed'];
        }
        return [$response, null];
    }

    $context = [
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'timeout' => 15,
        ],
    ];
    if ($method !== 'GET' && $data !== null) {
        $context['http']['content'] = $data;
    }
    $response = @file_get_contents($url, false, stream_context_create($context));
    if ($response === false) {
        return [null, 'Request failed'];
    }
    return [$response, null];
}

function loginUserSession($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role_name'] ?? 'user';
    $_SESSION['role_id'] = (int) ($user['role_id'] ?? 0);
    $_SESSION['avatar'] = $user['avatar'] ?? '';
}

function markUserLogin($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->execute([$userId]);
    logActivity($userId, 'login', 'User logged in via social');
}

$redirect = $_GET['redirect'] ?? '';
if ($redirect !== '') {
    $_SESSION['social_redirect'] = $redirect;
}

if (!isset($_GET['code'])) {
    $state = generateToken(16);
    $_SESSION['oauth_state'][$provider] = $state;

    if ($provider === 'google') {
        $params = [
            'response_type' => 'code',
            'client_id' => $socialConfig['google']['client_id'],
            'redirect_uri' => $socialConfig['google']['redirect_uri'],
            'scope' => $socialConfig['google']['scope'],
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
        header('Location: ' . $authUrl);
        exit();
    }

    if ($provider === 'facebook') {
        $params = [
            'client_id' => $socialConfig['facebook']['app_id'],
            'redirect_uri' => $socialConfig['facebook']['redirect_uri'],
            'state' => $state,
            'scope' => $socialConfig['facebook']['scope'],
            'response_type' => 'code',
        ];
        $authUrl = 'https://www.facebook.com/v19.0/dialog/oauth?' . http_build_query($params);
        header('Location: ' . $authUrl);
        exit();
    }
}

// Handle callback
$state = $_GET['state'] ?? '';
if ($state === '' || !isset($_SESSION['oauth_state'][$provider]) || $_SESSION['oauth_state'][$provider] !== $state) {
    http_response_code(400);
    echo 'Invalid OAuth state.';
    exit();
}
unset($_SESSION['oauth_state'][$provider]);

$code = $_GET['code'] ?? '';
if ($code === '') {
    http_response_code(400);
    echo 'Missing authorization code.';
    exit();
}

$profile = null;
if ($provider === 'google') {
    $postData = http_build_query([
        'code' => $code,
        'client_id' => $socialConfig['google']['client_id'],
        'client_secret' => $socialConfig['google']['client_secret'],
        'redirect_uri' => $socialConfig['google']['redirect_uri'],
        'grant_type' => 'authorization_code',
    ]);
    [$tokenResponse, $tokenError] = httpRequest(
        'https://oauth2.googleapis.com/token',
        'POST',
        $postData,
        ['Content-Type: application/x-www-form-urlencoded']
    );
    if ($tokenResponse === null) {
        http_response_code(500);
        echo 'Token request failed: ' . $tokenError;
        exit();
    }
    $tokenData = json_decode($tokenResponse, true);
    $accessToken = $tokenData['access_token'] ?? '';
    if ($accessToken === '') {
        http_response_code(500);
        echo 'Missing access token.';
        exit();
    }
    [$userResponse, $userError] = httpRequest(
        'https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . rawurlencode($accessToken),
        'GET'
    );
    if ($userResponse === null) {
        http_response_code(500);
        echo 'User request failed: ' . $userError;
        exit();
    }
    $userData = json_decode($userResponse, true);
    $profile = [
        'provider' => 'google',
        'id' => $userData['sub'] ?? '',
        'email' => $userData['email'] ?? '',
        'name' => $userData['name'] ?? '',
        'avatar' => $userData['picture'] ?? '',
    ];
}

if ($provider === 'facebook') {
    $params = [
        'client_id' => $socialConfig['facebook']['app_id'],
        'redirect_uri' => $socialConfig['facebook']['redirect_uri'],
        'client_secret' => $socialConfig['facebook']['app_secret'],
        'code' => $code,
    ];
    [$tokenResponse, $tokenError] = httpRequest(
        'https://graph.facebook.com/v19.0/oauth/access_token?' . http_build_query($params),
        'GET'
    );
    if ($tokenResponse === null) {
        http_response_code(500);
        echo 'Token request failed: ' . $tokenError;
        exit();
    }
    $tokenData = json_decode($tokenResponse, true);
    $accessToken = $tokenData['access_token'] ?? '';
    if ($accessToken === '') {
        http_response_code(500);
        echo 'Missing access token.';
        exit();
    }
    $userParams = [
        'fields' => 'id,name,email,picture.width(200).height(200)',
        'access_token' => $accessToken,
    ];
    [$userResponse, $userError] = httpRequest(
        'https://graph.facebook.com/me?' . http_build_query($userParams),
        'GET'
    );
    if ($userResponse === null) {
        http_response_code(500);
        echo 'User request failed: ' . $userError;
        exit();
    }
    $userData = json_decode($userResponse, true);
    $profile = [
        'provider' => 'facebook',
        'id' => $userData['id'] ?? '',
        'email' => $userData['email'] ?? '',
        'name' => $userData['name'] ?? '',
        'avatar' => $userData['picture']['data']['url'] ?? '',
    ];
}

if (!$profile || $profile['id'] === '') {
    http_response_code(500);
    echo 'Invalid profile response.';
    exit();
}

// Check for existing social account
$sql = "SELECT u.*, r.role_name
        FROM social_accounts s
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN roles r ON u.role_id = r.role_id
        WHERE s.provider = ? AND s.provider_user_id = ?
        LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute([$profile['provider'], $profile['id']]);
$user = $stmt->fetch();

if ($user) {
    if ($user['status'] !== 'active') {
        header('Location: login.php?message=account_locked');
        exit();
    }
    loginUserSession($user);
    markUserLogin($pdo, $user['user_id']);
    $redirect = $_SESSION['social_redirect'] ?? 'index.php';
    unset($_SESSION['social_redirect']);
    header('Location: ' . $redirect);
    exit();
}

// Try link existing email
if ($profile['email'] !== '') {
    $stmt = $pdo->prepare("SELECT u.*, r.role_name FROM users u LEFT JOIN roles r ON u.role_id = r.role_id WHERE u.email = ? LIMIT 1");
    $stmt->execute([$profile['email']]);
    $existing = $stmt->fetch();
    if ($existing) {
        if ($existing['status'] !== 'active') {
            header('Location: login.php?message=account_locked');
            exit();
        }
        $stmt = $pdo->prepare("INSERT INTO social_accounts (user_id, provider, provider_user_id, email, name, avatar) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $existing['user_id'],
            $profile['provider'],
            $profile['id'],
            $profile['email'],
            $profile['name'],
            $profile['avatar'],
        ]);
        loginUserSession($existing);
        markUserLogin($pdo, $existing['user_id']);
        $redirect = $_SESSION['social_redirect'] ?? 'index.php';
        unset($_SESSION['social_redirect']);
        header('Location: ' . $redirect);
        exit();
    }
}

// Send to social register
$_SESSION['social_profile'] = $profile;
header('Location: social-register.php');
exit();
