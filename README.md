# xmas-it

Proof of concept: turn **Philips Hue light strips** wrapped around a Christmas tree into animated garlands.

A small PHP script talks to your Hue bridge on the local network, picks a random effect from [`animation.json`](animation.json) (candle, rainbow, icy blue, fireworks...), plays it for a random duration, then switches to another one, forever.

## How it works

- Each effect defines random ranges for colour (`hue`, `sat`), brightness (`bri`) and `speed`.
- Every `speed` milliseconds, one light gets a new random colour picked in those ranges, with a smooth fade of the same length.
- Lights are updated one after the other, which gives a twinkling, "alive" look when several strips are on the tree.
- On `Ctrl+C` or `docker stop`, the lights are turned off cleanly (requires the `pcntl` extension, included in the Docker image).

## Requirements

- A Philips Hue bridge and one or more colour lights (Lightstrip Plus, Gradient Lightstrip, colour bulbs...)
- PHP 8.2+ with `curl` (and ideally `pcntl`) and Composer, **or** Docker
- The machine running the script must be on the same network as the bridge

## Setup

### 1. Find the bridge IP

In the Hue app (Settings > My Hue system > bridge), or open <https://discovery.meethue.com>.

### 2. Create an API token

Press the round **link button** on the bridge, then within 30 seconds:

```bash
curl -X POST http://<BRIDGE_IP>/api -d '{"devicetype":"xmas-it#tree"}'
```

The response contains `"username": "..."`: that is your `HUE_TOKEN`.

### 3. Configure

```bash
cp .env.example .env
```

| Variable         | Description                                               | Default          |
|------------------|-----------------------------------------------------------|------------------|
| `HUE_BRIDGE_IP`  | Local IP of the bridge                                    | required         |
| `HUE_TOKEN`      | API username from step 2                                  | required         |
| `LIGHTS`         | Light IDs to animate, comma separated (`1,4,7`)           | required         |
| `MIN_DURATION`   | Minimum duration of an effect, in seconds                 | `30`             |
| `MAX_DURATION`   | Maximum duration of an effect, in seconds                 | `120`            |
| `ANIMATION_FILE` | Path to the effects file                                  | `animation.json` |
| `DEBUG`          | Print every command sent to the bridge                    | `false`          |

To find your light IDs:

```bash
php main.php lights
```

## Run

### With PHP

```bash
composer install
php main.php
```

### With Docker

```bash
docker build -t xmas-it .
docker run --rm --env-file .env xmas-it
```

List the lights with `docker run --rm --env-file .env xmas-it php main.php lights`.

## Custom effects

Add an entry to `animation.json`. Every key needs integer `min` and `max` values:

```json
"candy_cane": {
  "speed": { "min": 800,   "max": 1500 },
  "hue":   { "min": 0,     "max": 1000 },
  "bri":   { "min": 150,   "max": 254 },
  "sat":   { "min": 0,     "max": 254 }
}
```

| Key     | Meaning                                                          | Range       |
|---------|------------------------------------------------------------------|-------------|
| `speed` | Delay between two light updates, and fade time (milliseconds)    | 1 to 60000  |
| `hue`   | Colour wheel: 0 red, ~12750 yellow, ~25500 green, ~46920 blue     | 0 to 65535  |
| `bri`   | Brightness                                                       | 0 to 254    |
| `sat`   | Saturation (0 is white)                                          | 0 to 254    |

The file is validated at startup; an invalid effect stops the script with an explicit message.

## Limitations

- Uses the Hue REST API v1: a strip is a single light, so the whole strip changes colour at once. Per-segment control on Gradient strips would need the Hue Entertainment API.
- The bridge handles roughly 10 light commands per second. Keep `speed` values above ~100 ms, especially with many lights, or updates will be dropped.
- **Security:** never expose your bridge to the internet (port forwarding). The API is plain HTTP and the token gives full control of your lights. Keep `.env` out of git (it is already in `.gitignore`).

## License

[MIT](LICENSE)
