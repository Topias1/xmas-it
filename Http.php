<?php

/**
 * Class Http
 * Handles HTTP requests for interacting with external APIs or devices.
 */
class Http {
    /**
     * Sends an HTTP GET request to a given URL.
     *
     * @param string $url The URL to send the request to.
     * @return array The response as an associative array.
     * @throws Exception If the request fails or returns an invalid response.
     */
    public function get($url) {
        $this->validateUrl($url);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->logError("GET request failed for URL $url: $error");
            throw new Exception("GET request failed: $error");
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            $this->logError("GET request returned HTTP code $httpCode for URL $url. Response: $response");
            throw new Exception("HTTP error $httpCode: $response");
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Invalid JSON response for GET request to $url: " . json_last_error_msg());
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        return $decodedResponse;
    }

    /**
     * Sends an HTTP PUT request to a given URL with a JSON payload.
     *
     * @param string $url The URL to send the request to.
     * @param array $data The data to send as JSON.
     * @return array The response as an associative array.
     * @throws Exception If the request fails or returns an invalid response.
     */
    public function put($url, $data) {
        $this->validateUrl($url);
        $this->validatePayload($data);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            $this->logError("PUT request failed for URL $url: $error");
            throw new Exception("PUT request failed: $error");
        }

        curl_close($ch);

        if ($httpCode >= 400) {
            $this->logError("PUT request returned HTTP code $httpCode for URL $url. Response: $response");
            throw new Exception("HTTP error $httpCode: $response");
        }

        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("Invalid JSON response for PUT request to $url: " . json_last_error_msg());
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        return $decodedResponse;
    }

    /**
     * Validates the URL.
     *
     * @param string $url The URL to validate.
     * @return void
     * @throws Exception If the URL is invalid.
     */
    private function validateUrl($url) {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $this->logError("Invalid URL: $url");
            throw new Exception("Invalid URL: $url");
        }
    }

    /**
     * Validates the payload for a POST request.
     *
     * @param array $data The data to validate.
     * @return void
     * @throws Exception If the payload is not an array or is empty.
     */
    private function validatePayload($data) {
        if (!is_array($data) || empty($data)) {
            $this->logError("Invalid payload for POST request: " . json_encode($data));
            throw new Exception("Invalid payload: Data must be a non-empty array.");
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
        file_put_contents('http_error_log.txt', "[$timestamp] $message\n", FILE_APPEND);
    }
}