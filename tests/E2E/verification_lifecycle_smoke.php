<?php

declare(strict_types=1);

$registerUrl = 'http://localhost:8000/api/v1/auth/register';
$verifyUrl = 'http://localhost:8000/api/v1/auth/email/verify-otp';
$email = 'verify_test_' . time() . '@test.com';

echo "1. Đăng ký tài khoản mới: {$email}\n";
$ch = curl_init($registerUrl);
$payload = json_encode([
    'name' => 'VerifyTester',
    'email' => $email,
    'password' => 'secret123456'
]);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($res, true);
$userId = $data['user']['id'];
echo "   --> HTTP {$code} | User ID: {$userId} | is_verified: " . ($data['user']['is_verified'] ? 'true' : 'false') . "\n";

// Đọc OTP trực tiếp từ Redis trong môi trường test
$redis = new Redis();
$redis->connect('ecom-redis', 6379);
$otp = $redis->get("flashcommerce_database_email_otp:{$userId}");
echo "2. Lấy OTP từ Redis: [{$otp}]\n";

echo "3. Gửi yêu cầu xác thực OTP đến: POST {$verifyUrl}\n";
$ch = curl_init($verifyUrl);
$verifyPayload = json_encode([
    'email' => $email,
    'otp' => $otp
]);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $verifyPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$verifyRes = curl_exec($ch);
$verifyCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   --> HTTP {$verifyCode} Response: {$verifyRes}\n";
