# MQTT Transport for Symfony Messenger

[![Latest Stable Version](https://poser.pugx.org/vs-point/messenger-transport-mqtt/version)](https://packagist.org/packages/vs-point/messenger-transport-mqtt)
[![Total Downloads](https://poser.pugx.org/vs-point/messenger-transport-mqtt/downloads)](https://packagist.org/packages/vs-point/messenger-transport-mqtt)
[![License](https://poser.pugx.org/vs-point/messenger-transport-mqtt/license)](https://packagist.org/packages/vs-point/messenger-transport-mqtt)

Symfony Messenger transport for MQTT, built on top of [php-mqtt/client](https://github.com/php-mqtt/client).

## Requirements

- PHP >= 8.2
- Symfony Messenger ^7.0 | ^8.0
- A running MQTT broker (e.g. [Eclipse Mosquitto](https://mosquitto.org))

## Installation

```bash
composer require vs-point/messenger-transport-mqtt
```

## How it works

**Sending:** any message class implementing `MqttMessageInterface` dispatched to the bus is published to the MQTT broker on the topic and with the QoS level returned by the message. Messages with QoS > 0 wait up to 300 ms for the broker acknowledgment before returning.

**Receiving:** the Messenger worker calls `get()` in a loop. Each call opens a 100 ms non-blocking poll against the broker and returns all messages that arrived during that window as `MqttMessage` envelopes. Subscribed topics are configured on the transport factory and established on the first `get()` call. All subscriptions use QoS 0.

`ack()` and `reject()` are no-ops — delivery guarantees are handled at the MQTT protocol level via QoS.

## Configuration

### 1. Register the transport factory

```yaml
# config/services.yaml
VSPoint\Messenger\Transport\Mqtt\MqttTransportFactory:
    arguments:
        $topics: ['/sensors/#', '/commands/+']
        $clientId: '%env(MQTT_CLIENT_ID)%'
    tags: ['messenger.transport_factory']
```

`$topics` — list of MQTT topic filters the worker subscribes to. Wildcards (`#`, `+`) are supported.  
`$clientId` — optional; defaults to the current process ID.

### 2. Configure the transport

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            mqtt: '%env(MESSENGER_MQTT_TRANSPORT_DSN)%'

        routing:
            App\Message\SensorReading: mqtt
```

### 3. Environment variables

```bash
# .env
MESSENGER_MQTT_TRANSPORT_DSN=mqtt://user:pass@broker.example.com:1883
MQTT_CLIENT_ID=my-app-worker
```

Omit credentials for brokers that allow anonymous connections:

```bash
MESSENGER_MQTT_TRANSPORT_DSN=mqtt://broker.example.com:1883
```

## Usage

### Sending a message

Implement `MqttMessageInterface` in your message class to control the topic, QoS level, and payload:

```php
<?php

namespace App\Message;

use VSPoint\Messenger\Transport\Mqtt\MqttMessageInterface;

final class SensorReading implements MqttMessageInterface
{
    public function __construct(
        private readonly string $sensorId,
        private readonly float $value,
    ) {
    }

    public function getTopic(): string
    {
        return '/sensors/' . $this->sensorId;
    }

    public function getQos(): int
    {
        return 1; // at least once
    }

    public function getBody(): string
    {
        return (string) $this->value;
    }
}
```

Dispatch it via the message bus:

```php
$bus->dispatch(new SensorReading('temp-01', 23.4));
```

### Receiving messages

Start the Messenger worker:

```bash
php bin/console messenger:consume mqtt
```

The worker receives `MqttMessage` envelopes. Handle them with a message handler:

```php
<?php

namespace App\MessageHandler;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use VSPoint\Messenger\Transport\Mqtt\MqttMessage;

#[AsMessageHandler]
final class MqttMessageHandler
{
    public function __invoke(MqttMessage $message): void
    {
        // $message->getTopic()  — MQTT topic the message arrived on
        // $message->getBody()   — raw payload string
        // $message->getQos()    — QoS level (always 0 for received messages)
        // $message->getId()     — unique ID generated on receipt
    }
}
```

> **Note:** received messages are always wrapped in `MqttMessage`, regardless of the original message class used for sending. The transport does not perform any serialization — `getBody()` carries the raw string payload.

## Development

### Start the local environment

```bash
docker compose build
docker compose up -d mqtt
docker compose run --rm php composer install
```

### Run tests

Tests require the MQTT broker to be running (`docker compose up -d mqtt`):

```bash
docker compose run --rm php ./vendor/bin/phpunit --testdox
```

### Static analysis

```bash
docker compose run --rm php ./vendor/bin/phpstan analyse --memory-limit=512M
```

### Coding standard

```bash
# check
docker compose run --rm php ./vendor/bin/ecs check

# fix
docker compose run --rm php ./vendor/bin/ecs check --fix
```
