<?php
/**
 * Payment gateway configuration.
 *
 * This file is intended for sandbox/test integration only. Fill in the
 * credentials provided by the payment gateway for sandbox mode.
 *
 * ZaloPay sandbox docs: https://developers.zalopay.vn/docs
 * Momo sandbox docs: https://developers.momo.vn
 *
 * Note: For production use, store secrets outside the webroot or in environment
 * variables and keep them out of version control.
 */

return [
    'momo' => [
        // Sandbox credentials (replace with your own)
        'partner_code' => '',
        'access_key' => '',
        'secret_key' => '',
        'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
        // Pay URL returned in response.
        'return_url' => 'http://localhost/GW_VN%20Ver%20Final/donate.php',
        'notify_url' => 'http://localhost/GW_VN%20Ver%20Final/api/momo_notify.php',
    ],
    'zalopay' => [
        // Sandbox credentials (replace with your own)
        'app_id' => '',
        'key1' => '',
        'key2' => '',
        'endpoint' => 'https://sb-openapi.zalopay.vn/v2/create',
        'return_url' => 'http://localhost/GW_VN%20Ver%20Final/donate.php',
    ],
    'bank_transfer' => [
        // Bank account details to show for manual transfer
        'account_name' => 'Organization Name',
        'account_number' => '0123456789',
        'bank_name' => 'Ngân hàng ABC',
        'branch' => 'Chi nhánh XYZ',
        'note' => 'Vui lòng ghi "Quyên góp" và mã giao dịch trong nội dung chuyển khoản.',
    ],
];
