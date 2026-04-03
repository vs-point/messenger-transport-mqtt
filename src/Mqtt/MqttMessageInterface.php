<?php

declare(strict_types=1);

namespace VSPoint\Messenger\Transport\Mqtt;

interface MqttMessageInterface
{
    public function getTopic(): string;

    public function getQos(): int;

    public function getBody(): string;
}
