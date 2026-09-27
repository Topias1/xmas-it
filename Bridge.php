<?php

declare(strict_types=1);

require_once __DIR__ . '/Http.php';

/**
 * Thin wrapper around the Philips Hue bridge REST API (v1).
 */
final class Bridge
{
    private readonly string $baseUrl;

    public function __construct(string $ip, string $token, private readonly Http $http = new Http())
    {
        if ($ip === '' || $token === '') {
            throw new InvalidArgumentException('HUE_BRIDGE_IP and HUE_TOKEN must be set.');
        }
        $this->baseUrl = sprintf('http://%s/api/%s', $ip, rawurlencode($token));
    }

    /**
     * Fails fast if the bridge is unreachable or the token is not authorized.
     * (`/config` answers even to unknown tokens, so `/lights` is used instead.)
     */
    public function assertReachable(): void
    {
        $this->getLights();
    }

    /** @return array<string, array<mixed>> Lights keyed by ID. */
    public function getLights(): array
    {
        return $this->call('GET', '/lights');
    }

    /** @param array<string, mixed> $state */
    public function setState(string $lightId, array $state): void
    {
        $this->call('PUT', '/lights/' . rawurlencode($lightId) . '/state', $state);
    }

    public function turnOn(string $lightId): void
    {
        $this->setState($lightId, ['on' => true]);
    }

    public function turnOff(string $lightId): void
    {
        $this->setState($lightId, ['on' => false]);
    }

    /**
     * The Hue API reports errors with HTTP 200 and a body like
     * [{"error": {"type": 1, "description": "unauthorized user"}}].
     *
     * @param array<string, mixed>|null $data
     * @return array<mixed>
     */
    private function call(string $method, string $path, ?array $data = null): array
    {
        $url = $this->baseUrl . $path;
        $response = $method === 'GET' ? $this->http->get($url) : $this->http->put($url, $data ?? []);

        $errors = array_filter(
            array_is_list($response) ? $response : [],
            static fn ($item) => is_array($item) && isset($item['error']),
        );
        if ($errors !== []) {
            $messages = array_map(
                static fn ($item) => ($item['error']['description'] ?? 'unknown error'),
                $errors,
            );
            throw new RuntimeException("Hue bridge error on $path: " . implode('; ', $messages));
        }

        return $response;
    }
}
