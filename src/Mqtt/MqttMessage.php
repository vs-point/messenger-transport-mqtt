<?php

declare(strict_types=1);

namespace VSPoint\Messenger\Transport\Mqtt;

class MqttMessage implements MqttMessageInterface
{
    public function __construct(
        private readonly string $topic,
        private readonly int $qos,
        private readonly string $body,
        private readonly string $id,
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

    public function getId(): string
    {
        return $this->id;
    }
}
