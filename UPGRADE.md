# Upgrade Guide

## Upgrading to v1.0.0

### Requirements

- PHP **>= 8.2** (previously ~7.1)
- Symfony Messenger **^7.0 | ^8.0** (previously ~4.2)

### Replace `ext-mosquitto` with `php-mqtt/client`

The C extension `ext-mosquitto` (PECL) is not compatible with PHP 8.x and has been replaced by the pure-PHP library [`php-mqtt/client`](https://github.com/php-mqtt/client). Remove the extension from your system requirements — no PHP extension is needed anymore.

### Update `MqttTransportFactory` service definition

The `SerializerInterface` constructor argument has been removed. Update your service definition:

```yaml
# config/services.yaml

# Before
VSPoint\Messenger\Transport\Mqtt\MqttTransportFactory:
    arguments:
        $topics: ['/topic1', '/topic2']
        $serializer: '@serializer'
        $clientId: '%env(MQTT_CLIENT_ID)%'
    tags: ['messenger.transport_factory']

# After
VSPoint\Messenger\Transport\Mqtt\MqttTransportFactory:
    arguments:
        $topics: ['/topic1', '/topic2']
        $clientId: '%env(MQTT_CLIENT_ID)%'
    tags: ['messenger.transport_factory']
```

### `receive()` and `stop()` removed from `MqttTransport`

These methods were part of the old Symfony Messenger 4.x transport interface and no longer exist. Use the standard Symfony Messenger worker instead:

```bash
php bin/console messenger:consume mqtt
```

### `send()` return type changed

`MqttTransport::send()` previously returned `void`. It now returns `Envelope` as required by the Symfony Messenger 7 `SenderInterface`. This only affects code that calls the transport directly rather than through the message bus.
