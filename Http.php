<?php

declare(strict_types=1);

/**
 * Minimal JSON-over-HTTP client built on cURL.
 */
final class Http
{
    public function __construct(
        private readonly int $connectTimeout = 3,
        private readonly int $timeout = 5,
    ) {
    }

    /** @return array<mixed> */
    public function get(string $url): array
    {
        return $this->request('GET', $url);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<mixed>
     */
    public function put(string $url, array $data): array
    {
        return $this->request('PUT', $url, $data);
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<mixed>
     */
    private function request(string $method, string $url, ?array $data = null): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_THROW_ON_ERROR));
        }

        $body = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false) {
            throw new RuntimeException("$method request failed: $error");
        }
        if ($status >= 400) {
            throw new RuntimeException("$method request returned HTTP $status");
        }

        $decoded = json_decode((string) $body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException("$method request returned invalid JSON");
        }

        return $decoded;
    }
}
