<?php

declare(strict_types=1);

require_once __DIR__ . '/Bridge.php';

/**
 * Plays random effects from animation.json on a set of Hue lights, forever.
 *
 * Each effect defines min/max ranges for:
 *   - speed: delay in milliseconds between two light updates (also used as fade time)
 *   - hue:   0..65535
 *   - bri:   0..254
 *   - sat:   0..254
 */
final class Animation
{
    private const LIMITS = [
        'speed' => [1, 60000],
        'hue' => [0, 65535],
        'bri' => [0, 254],
        'sat' => [0, 254],
    ];

    /** Delay before retrying after an error, to avoid hammering the bridge. */
    private const ERROR_BACKOFF_SECONDS = 5;

    private bool $running = true;

    /**
     * @param string[] $lights Light IDs to animate.
     * @param array<string, array<string, array{min: int, max: int}>> $effects
     */
    public function __construct(
        private readonly Bridge $bridge,
        private readonly array $lights,
        private readonly array $effects,
        private readonly int $minDuration,
        private readonly int $maxDuration,
        private readonly bool $debug = false,
    ) {
        if ($lights === []) {
            throw new InvalidArgumentException('LIGHTS must contain at least one light ID.');
        }
        if ($effects === []) {
            throw new InvalidArgumentException('No effect defined in the animation file.');
        }
        if ($minDuration < 1 || $minDuration > $maxDuration) {
            throw new InvalidArgumentException('Expected 1 <= MIN_DURATION <= MAX_DURATION.');
        }
    }

    /**
     * Loads and validates an effects file.
     *
     * @return array<string, array<string, array{min: int, max: int}>>
     */
    public static function loadEffects(string $path): array
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Unable to read animation file: $path");
        }

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new RuntimeException('Animation file must be a JSON object of named effects.');
        }

        foreach ($data as $name => $effect) {
            foreach (self::LIMITS as $key => [$lower, $upper]) {
                $min = $effect[$key]['min'] ?? null;
                $max = $effect[$key]['max'] ?? null;
                if (!is_int($min) || !is_int($max)) {
                    throw new RuntimeException("Effect '$name': '$key.min' and '$key.max' must be integers.");
                }
                if ($min > $max || $min < $lower || $max > $upper) {
                    throw new RuntimeException("Effect '$name': expected $lower <= $key.min <= $key.max <= $upper.");
                }
            }
        }

        return $data;
    }

    /**
     * Runs random effects until SIGINT/SIGTERM, then turns the lights off.
     */
    public function run(): void
    {
        $this->installSignalHandlers();

        while ($this->running) {
            try {
                $this->playRandomEffect();
            } catch (Throwable $e) {
                self::log('Error: ' . $e->getMessage());
                $this->sleepMs(self::ERROR_BACKOFF_SECONDS * 1000);
            }
        }

        $this->turnAllOff();
    }

    private function playRandomEffect(): void
    {
        $name = array_rand($this->effects);
        $effect = $this->effects[$name];
        $duration = random_int($this->minDuration, $this->maxDuration);
        self::log("Playing '$name' for {$duration}s");

        foreach ($this->lights as $lightId) {
            $this->bridge->turnOn($lightId);
        }

        $end = microtime(true) + $duration;
        while ($this->running && microtime(true) < $end) {
            foreach ($this->lights as $lightId) {
                if (!$this->running || microtime(true) >= $end) {
                    return;
                }
                $speed = $this->pick($effect['speed']);
                $state = [
                    'hue' => $this->pick($effect['hue']),
                    'bri' => $this->pick($effect['bri']),
                    'sat' => $this->pick($effect['sat']),
                    // Hue transition time is in 100 ms steps.
                    'transitiontime' => intdiv($speed, 100),
                ];
                if ($this->debug) {
                    self::log("  light $lightId " . json_encode($state));
                }
                $this->bridge->setState($lightId, $state);
                $this->sleepMs($speed);
            }
        }
    }

    /** @param array{min: int, max: int} $range */
    private function pick(array $range): int
    {
        return random_int($range['min'], $range['max']);
    }

    private function turnAllOff(): void
    {
        foreach ($this->lights as $lightId) {
            try {
                $this->bridge->turnOff($lightId);
            } catch (Throwable $e) {
                self::log("Could not turn off light $lightId: " . $e->getMessage());
            }
        }
        self::log('Lights off, bye.');
    }

    private function installSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }
        pcntl_async_signals(true);
        $stop = function (): void {
            $this->running = false;
        };
        pcntl_signal(SIGINT, $stop);
        pcntl_signal(SIGTERM, $stop);
    }

    /** Sleeps in small slices so a stop signal is honoured quickly. */
    private function sleepMs(int $ms): void
    {
        $end = microtime(true) + $ms / 1000;
        while ($this->running && ($left = $end - microtime(true)) > 0) {
            usleep((int) (min($left, 0.1) * 1_000_000));
        }
    }

    public static function log(string $message): void
    {
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . "] $message\n");
    }
}
