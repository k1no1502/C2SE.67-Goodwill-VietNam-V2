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
        // MoMo sandbox test credentials (from https://github.com/momo-wallet/payment)
        'partner_code' => 'MOMOBKUN20180529',
        'access_key'  => 'klm05TvNBzhg7h7j',
        'secret_key'  => 'at67qH6mk8w5Y1nAyMoYKMWACiEi2bsa',
        'endpoint'    => 'https://test-payment.momo.vn/v2/gateway/api/create',
        'request_type' => 'captureWallet',
        'partner_name' => 'Test',
        'store_id'    => 'MomoTestStore',
        // URL MoMo redirect sau khi thanh toán
        'return_url'  => 'http://localhost/donate.php',
        'notify_url'  => 'http://localhost/api/momo_notify.php',
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
