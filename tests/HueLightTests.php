<?php

use PHPUnit\Framework\TestCase;

class HueLightsTest extends TestCase
{
    private $bridge;

    protected function setUp(): void
    {
        // Initialize the bridge object with mock or real data
        $this->bridge = new Bridge('your-bridge-ip', 'your-token');
    }

    public function testLightStatus()
    {
        $lightId = 3;

        $status = $this->bridge->getLightStatus($lightId);

        // Example assertions
        $this->assertIsArray($status, 'Light status should be an array');
        $this->assertArrayHasKey('state', $status, 'Light status should contain state');
        $this->assertTrue(isset($status['state']['on']), 'Light state should have an "on" key');
    }
}