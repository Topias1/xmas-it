<?php

require_once __DIR__ . '/vendor/autoload.php';
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

require_once __DIR__ . '/Bridge.php';
$bridge = new Bridge();

/**
 * Class Animation
 * Handles animations for a set of lights connected to a Philips Hue bridge.
 */
class Animation {
    /**
     * @var Bridge The bridge instance used to communicate with the lights.
     */
    private $bridge;

    /**
     * @var array The configuration for various animations, loaded from a JSON file.
     */
    private $animations;

    /**
     * @var array The list of light IDs to be controlled.
     */
    private $lights;

    /**
     * @var int Minimum effect duration in seconds
     */
    private $minimumDuration;

    /**
     * @var int Maximum effect duration in seconds
     */
    private $maximumDuration;

    /**
     * Animation constructor.
     * Initializes the bridge and loads animation configurations.
     *
     * @param Bridge $bridge The bridge instance used to control the lights.
     * @throws Exception If the animation configuration file cannot be loaded or contains invalid JSON.
     */
    public function __construct(Bridge $bridge) {
        $this->bridge = $bridge;
        $this->lights = explode('|', $_ENV['LIGHTS']);
        $json = file_get_contents('animation.json');
        if ($json === false) {
            throw new Exception("Unable to load animation configuration file.");
        }
        $this->animations = $this->validateJson(file_get_contents('animation.json'));
        $this->minimumDuration = $_ENV['MIN_DURATION'];
        $this->maximumDuration = $_ENV['MAX_DURATION'];
    }

    /**
     * Validates and parses the animation JSON file.
     *
     * @param string $json The JSON content to validate.
     * @return array Parsed JSON data.
     * @throws Exception If the JSON is invalid or does not match the required schema.
     */
    private function validateJson($json) {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON format: " . json_last_error_msg());
        }

        $requiredKeys = ['speed', 'hue', 'bri', 'sat'];
        foreach ($data as $animation => $properties) {
            foreach ($requiredKeys as $key) {
                if (!isset($properties[$key])) {
                    throw new Exception("Missing key '$key' in animation '$animation'");
                }

                foreach (['min', 'max'] as $subKey) {
                    if (!isset($properties[$key][$subKey])) {
                        throw new Exception("Missing '$subKey' for '$key' in animation '$animation'");
                    }

                    if (!is_int($properties[$key][$subKey])) {
                        throw new Exception("'$subKey' for '$key' in animation '$animation' must be an integer.");
                    }
                }

                if ($properties[$key]['min'] > $properties[$key]['max']) {
                    throw new Exception("'min' must be less than 'max' for '$key' in animation '$animation'");
                }
            }
        }

        return $data;
    }

    /**
     * Loads a random animation effect on the lights defined in the configuration.
     *
     * @return void
     */
    public function loadEffect() {
        foreach ($this->lights as $lightId) {
            $this->bridge->turnOnLight($lightId);
        }
        $animationNames = array_keys($this->animations);
        $randomAnimationName = $animationNames[array_rand($animationNames)];
        $config = $this->animations[$randomAnimationName];
        $duration = rand($this->minimumDuration, $this->maximumDuration);

        for ($i = 0; $i < $duration; $i++) {
            foreach ($this->lights as $lightId) {
                $speed = rand($config['speed']['min'], $config['speed']['max']);
                $hue = rand($config['hue']['min'], $config['hue']['max']);
                $brightness = rand($config['bri']['min'], $config['bri']['max']);
                $saturation = rand($config['sat']['min'], $config['sat']['max']);
                $response = $this->bridge->setLightColor($lightId, $hue, $brightness, $saturation);
                usleep($speed);
            }
        }
    }

    /**
     * Launches an infinite animation loop on the lights defined in the configuration.
     *
     * @return void
     */
    public function launch() {
        while (true) {
            try {
                $this->loadEffect();
            } catch (Exception $e) {
                $this->logError($e->getMessage());
                echo "An error occurred: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Outputs debug information for a request to the lights.
     *
     * @param int $lightId The ID of the light being controlled.
     * @param int $hue The hue value for the light color.
     * @param int $bri The brightness value for the light.
     * @param int $sat The saturation value for the light.
     * @return void
     */
    private function debugRequest($lightId, $hue, $bri, $sat) {
        echo "Light ID: $lightId | Hue: $hue | Brightness: $bri | Saturation: $sat\n";
    }

    /**
     * Outputs debug information for a response from the lights.
     *
     * @param int $lightId The ID of the light being controlled.
     * @param mixed $response The response from the bridge.
     * @return void
     */
    private function debugResponse($lightId, $response) {
        echo "Response for Light ID $lightId: " . json_encode($response) . "\n";
    }

    /**
     * Logs an error message to a file.
     *
     * @param string $message The error message to log.
     * @return void
     */
    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents('error_log.txt', "[$timestamp] $message\n", FILE_APPEND);
    }
}