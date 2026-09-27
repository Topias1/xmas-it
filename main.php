<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/Animation.php';

Dotenv\Dotenv::createImmutable(__DIR__)->safeLoad();

function env(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return is_string($value) && $value !== '' ? trim($value) : $default;
}

try {
    $bridge = new Bridge(env('HUE_BRIDGE_IP'), env('HUE_TOKEN'));
    $bridge->assertReachable();

    // `php main.php lights` lists the lights known by the bridge, to fill LIGHTS.
    if (($argv[1] ?? null) === 'lights') {
        foreach ($bridge->getLights() as $id => $light) {
            printf("%-4s %-30s %s\n", $id, $light['name'] ?? '?', $light['type'] ?? '');
        }
        exit(0);
    }

    $lights = array_values(array_filter(array_map('trim', preg_split('/[,|]/', env('LIGHTS')))));

    $animation = new Animation(
        $bridge,
        $lights,
        Animation::loadEffects(env('ANIMATION_FILE', __DIR__ . '/animation.json')),
        (int) env('MIN_DURATION', '30'),
        (int) env('MAX_DURATION', '120'),
        filter_var(env('DEBUG', 'false'), FILTER_VALIDATE_BOOL),
    );
    $animation->run();
} catch (Throwable $e) {
    Animation::log('Fatal: ' . $e->getMessage());
    exit(1);
}
