<?php

require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Instantiate the Http handler and pass it to the Bridge class
require_once __DIR__ . '/Http.php';

/**
 * Class Bridge
 * Manages connections and operations with Philips Hue lights.
 */
class Bridge {
    private $ip;
    private $token;
    private $http;

    /**
     * Bridge constructor.
     * Initializes the bridge IP, token, and HTTP handler.
     */
    public function __construct() {
        $this->ip = ($_ENV['DEV'] ? $_ENV['HUE_BRIDGE_LOCAL_IP'] : $_ENV['HUE_BRIDGE_REMOTE_IP']);
        $this->token = $_ENV['HUE_TOKEN'];
        $this->http = new Http();

        $this->validateBridgeConnection();
    }

    /**
     * Validates the connection to the Philips Hue Bridge.
     *
     * @throws Exception If the bridge is unreachable or credentials are invalid.
     */
    private function validateBridgeConnection() {
        $url = sprintf('http://%s/api/%s/config', $this->ip, $this->token);

        try {
            $response = $this->http->get($url);
            if (!isset($response['name'])) {
                $this->logError("Invalid response from Hue Bridge: " . json_encode($response));
                throw new Exception("Unable to connect to the Hue Bridge. Check your IP and token.");
            }
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            throw new Exception("Bridge validation failed: " . $e->getMessage());
        }
    }

    /**
     * Generates the API URL for a specific light.
     *
     * @param string $lightId The ID of the light.
     * @return string The API URL for the light.
     */
    private function getUrl($lightId) {
        $this->validateLightId($lightId);
        return sprintf('http://%s/api/%s/lights/%s/state', $this->ip, $this->token, $lightId);
    }

    /**
     * Turns on a light.
     *
     * @param string $lightId The ID of the light.
     * @return array|null The API response as an associative array, or null on failure.
     * @throws Exception If the operation fails.
     */
    public function turnOnLight($lightId) {
        try {
            $url = $this->getUrl($lightId);
            $data = ["on" => true];
            return $this->http->put($url, $data);
        } catch (Exception $e) {
            $this->logError("Failed to turn on light $lightId: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Turns off a light.
     *
     * @param string $lightId The ID of the light.
     * @return array|null The API response as an associative array, or null on failure.
     * @throws Exception If the operation fails.
     */
    public function turnOffLight($lightId) {
        try {
            $url = $this->getUrl($lightId);
            $data = ["on" => false];
            return $this->http->put($url, $data);
        } catch (Exception $e) {
            $this->logError("Failed to turn off light $lightId: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sets the color, brightness, and saturation of a light.
     *
     * @param string $lightId The ID of the light.
     * @param int $hue The hue value (0-65535).
     * @param int $brightness The brightness value (0-254).
     * @param int $saturation The saturation value (0-254).
     * @return array|null The API response as an associative array, or null on failure.
     * @throws Exception If the operation fails.
     */
    public function setLightColor($lightId, $hue, $brightness, $saturation) {
        $this->validateColorValues($hue, $brightness, $saturation);

        try {
            $url = $this->getUrl($lightId);
            $data = [
                "hue" => $hue,
                "bri" => $brightness,
                "sat" => $saturation
            ];
            return $this->http->put($url, $data);
        } catch (Exception $e) {
            $this->logError("Failed to set light color for $lightId: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Retrieves the list of available lights from the bridge.
     *
     * @return array|null The list of lights as an associative array, or null on failure.
     * @throws Exception If the operation fails.
     */
    public function getAvailableLights() {
        try {
            $url = sprintf('http://%s/api/%s/lights', $this->ip, $this->token);
            return $this->http->get($url);
        } catch (Exception $e) {
            $this->logError("Failed to retrieve available lights: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validates the light ID.
     *
     * @param string $lightId The light ID to validate.
     * @throws Exception If the light ID is invalid.
     */
    private function validateLightId($lightId) {
        if (!is_string($lightId) || empty($lightId)) {
            $this->logError("Invalid light ID: $lightId");
            throw new Exception("Invalid light ID: $lightId");
        }
    }

    /**
     * Validates color values for lights.
     *
     * @param int $hue The hue value.
     * @param int $brightness The brightness value.
     * @param int $saturation The saturation value.
     * @throws Exception If any value is out of range.
     */
    private function validateColorValues($hue, $brightness, $saturation) {
        if ($hue < 0 || $hue > 65535) {
            $this->logError("Invalid hue value: $hue. Expected range is 0 to 65535.");
            throw new Exception("Invalid hue value: $hue. Expected range is 0 to 65535.");
        }
        if ($brightness < 0 || $brightness > 254) {
            $this->logError("Invalid brightness value: $brightness. Expected range is 0 to 254.");
            throw new Exception("Invalid brightness value: $brightness. Expected range is 0 to 254.");
        }
        if ($saturation < 0 || $saturation > 254) {
            $this->logError("Invalid saturation value: $saturation. Expected range is 0 to 254.");
            throw new Exception("Invalid saturation value: $saturation. Expected range is 0 to 254.");
        }
    }

    /**
     * Logs an error message to a file.
     *
     * @param string $message The error message to log.
     * @return void
     */
    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents('bridge_error_log.txt', "[$timestamp] $message\n", FILE_APPEND);
    }
}