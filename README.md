# MQTT Transport for Symfony Messenger

[![Latest Stable Version](https://poser.pugx.org/vs-point/messenger-transport-mqtt/version)](https://packagist.org/packages/vs-point/messenger-transport-mqtt)
[![Total Downloads](https://poser.pugx.org/vs-point/messenger-transport-mqtt/downloads)](https://packagist.org/packages/vs-point/messenger-transport-mqtt)
[![License](https://poser.pugx.org/vs-point/messenger-transport-mqtt/license)](https://packagist.org/packages/vs-point/messenger-transport-mqtt)
[![Latest Unstable Version](https://poser.pugx.org/vs-point/messenger-transport-mqtt/v/unstable)](//packagist.org/packages/vs-point/messenger-transport-mqtt)

Extends the [Symfony Messenger](https://symfony.com/doc/master/components/messenger.html) component to
handle the MQTT transport.

## Install

```bash
composer require vs-point/messenger-transport-mqtt
```

### Install without the Symfony Bundle:
1. Register the transport factory:

```yaml
#  config/services.yaml
VSPoint\Mqtt\MqttTransportFactory:
    arguments:
        $topics: ['/topic1','/topic2']
        $clientId: '%env(MQTT_CLIENT_ID)%'
    tags: ['messenger.transport_factory']
```

2. Configure the MQTT transport:
```yaml
#  config/packages/messenger.yaml
framework:
    messenger:
        transports:
            mqtt: '%env(MESSENGER_MQTT_TRANSPORT_DSN)%'

        routing:
            # Route your messages to the transports
            '*': mqtt
```

## Configuration

Example:
```bash
# .env
MESSENGER_MQTT_TRANSPORT_DSN=mqtt://user:pass@server.com:13193
MQTT_CLIENT_ID=symfonyclient
```