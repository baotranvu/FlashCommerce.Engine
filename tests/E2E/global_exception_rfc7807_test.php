<?php

declare(strict_types=1);

$registerUrl = 'http://localhost:8000/api/v1/auth/register';
$verifyUrl = 'http://localhost:8000/api/v1/auth/email/verify-otp';
$resendUrl = 'http://localhost:8000/api/v1/auth/email/resend-otp';
$email = 'rfc7807_test_' . time() . '@test.com';

echo "1. Đăng ký tài khoản: {$email}\n";
$ch = curl_init($registerUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => 'RFCTester', 'email' => $email, 'password' => 'secret123456']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$regRes = curl_exec($ch);
curl_close($ch);

echo "2. Bắn sai mã OTP ('000000') để kích hoạt InvalidOtpException (422):\n";
$ch = curl_init($verifyUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'otp' => '000000']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$errRes = curl_exec($ch);
$errCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   --> HTTP {$errCode} Response: {$errRes}\n\n";

echo "3. Bắn liên tiếp Resend OTP để kích hoạt CooldownActiveException (429) kèm Retry-After:\n";
$ch = curl_init($resendUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true); // Include response headers
$rawRes = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$resendCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$headers = substr($rawRes, 0, $headerSize);
$body = substr($rawRes, $headerSize);

// Extract Retry-After header
preg_match('/Retry-After:\s*(\d+)/i', $headers, $matches);
$retryAfterHeader = $matches[1] ?? 'NOT_FOUND';

echo "   --> HTTP {$resendCode} (Header 'Retry-After: {$retryAfterHeader}')\n";
echo "   --> RFC 7807 Body: {$body}\n";
