<?php

declare(strict_types=1);

namespace FlashCommerce\Identity\Tests\E2E;

use PHPUnit\Framework\TestCase;
use Redis;
use RuntimeException;

abstract class IdentityE2ETestCase extends TestCase
{
    protected string $baseUrl = 'http://127.0.0.1:8000';
    private ?Redis $redis = null;

    protected function getRedis(): Redis
    {
        if ($this->redis === null) {
            $this->redis = new Redis();
            $host = $_ENV['REDIS_HOST'] ?? $_SERVER['REDIS_HOST'] ?? getenv('REDIS_HOST') ?: 'ecom-redis';
            $port = (int) ($_ENV['REDIS_PORT'] ?? $_SERVER['REDIS_PORT'] ?? getenv('REDIS_PORT') ?: 6379);

            try {
                $connected = @$this->redis->connect($host, $port, 2.0);
            } catch (\Throwable) {
                $connected = false;
            }

            if (!$connected) {
                try {
                    $connected = @$this->redis->connect('127.0.0.1', 6379, 2.0);
                } catch (\Throwable) {
                    $connected = false;
                }
            }

            if (!$connected) {
                throw new RuntimeException("Failed to connect to Redis server at [{$host}:{$port}] or [127.0.0.1:6379]");
            }

            // Sync with Laravel default Redis prefix: flashcommerce_database_
            $this->redis->setOption(Redis::OPT_PREFIX, 'flashcommerce_database_');
        }

        return $this->redis;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array{status: int, body: array<string, mixed>, headers: array<string, string>, raw_body: string}
     */
    protected function request(string $method, string $uri, array $payload = [], array $headers = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($uri, '/');
        $ch = curl_init($url);

        $defaultHeaders = ['Content-Type: application/json', 'Accept: application/json'];
        foreach ($headers as $k => $v) {
            $defaultHeaders[] = "{$k}: {$v}";
        }

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $defaultHeaders);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if (!empty($payload)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }

        $rawResponse = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $rawHeaderStr = substr($rawResponse, 0, $headerSize);
        $rawBodyStr = substr($rawResponse, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $rawHeaderStr) as $line) {
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $parsedHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        $parsedBody = json_decode($rawBodyStr, true);

        return [
            'status' => $status,
            'body' => is_array($parsedBody) ? $parsedBody : [],
            'headers' => $parsedHeaders,
            'raw_body' => $rawBodyStr,
        ];
    }
}
