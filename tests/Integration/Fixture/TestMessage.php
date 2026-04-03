<?php

declare(strict_types=1);

namespace VSPoint\Messenger\Transport\Tests\Integration\Fixture;

use VSPoint\Messenger\Transport\Mqtt\MqttMessageInterface;

final class TestMessage implements MqttMessageInterface
{
    public function __construct(
        private readonly string $topic,
        private readonly int $qos,
        private readonly string $body,
    ) {
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getQos(): int
    {
        return $this->qos;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
