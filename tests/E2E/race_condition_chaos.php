<?php

declare(strict_types=1);

$targetUrl = 'http://localhost:8000/api/v1/auth/register';
$email = 'race_victim_' . time() . '@test.com';

echo "🚀 [CHAOS TEST] Bắn đồng thời 2 HTTP Request với cùng email: {$email}\n";

$mh = curl_multi_init();
$handles = [];

for ($i = 1; $i <= 2; $i++) {
    $ch = curl_init($targetUrl);
    $payload = json_encode([
        'name' => "Racer {$i}",
        'email' => $email,
        'password' => 'secret123456'
    ]);

    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

echo "\n📊 KẾT QUẢ PHẢN HỒI TỪ SERVER:\n";
foreach ($handles as $idx => $ch) {
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response = curl_multi_getcontent($ch);
    echo "--- Request #" . ($idx + 1) . " (HTTP {$status}) ---\n";
    echo $response . "\n";
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);
