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
MESSENGER_MQTT_TRANSPORT_DSN=mqtt://user:pass@server:1883
MQTT_CLIENT_ID=symfonyclient
```

## Usage

```php
<?php

namespace App;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Scenario {

    /** @var MessageBusInterface  */
    private $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function __invoke(string $section = 'ALL')
    {
        $this->bus->dispatch(new StateMessage($section, 'newState'));
    }

}
```

```php
<?php

namespace App;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class Controller {

    /** @var MessageBusInterface  */
    private $bus;

    /** @var RouterInterface */
    private $router;

    /** @var Scenario  */
    private $stateScenario;

    public function __construct(MessageBusInterface $bus, RouterInterface $router, Scenario $stateScenario)
    {
        $this->bus = $bus;
        $this->router = $router;
        $this->stateScenario = $stateScenario;
    }

    /**
     * @Route("/state/{section}",
     *     name="change.state",
     *     requirements={"
     *          section"="all|a|b|c|((25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?))"
     *     },
     *     defaults={"section": "all"})
     */
    public function __invoke($section)
    {
        $stateScenario = $this->stateScenario;
        $stateScenario($section);

        return new RedirectResponse($this->router->generate('homepage'), 302);
    }

}
```

```php
<?php

namespace App;

use VSPoint\Messenger\Transport\Mqtt\MqttMessage;
use VSPoint\Messenger\Transport\Mqtt\MqttMessageInterface;

class StateMessage implements MqttMessageInterface
{
    public function __construct(string $section, string $state)
    {
        $this->topic = '/state/'.$section;
        $this->message = $state;
    }

    private $topic;
    private $message;

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getQos(): int
    {
        return 1;
    }

    public function getBody(): string
    {
        return $this->message;
    }

}
```