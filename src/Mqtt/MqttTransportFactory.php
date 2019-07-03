<?php

namespace VSPoint\Messenger\Transport\Mqtt;

use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MqttTransportFactory implements TransportFactoryInterface
{
    private $serializer;
    private $topics;
    private $clientId;

    public function __construct(array $topics, ?string $clientId = null)
    {
        $this->topics = $topics;
        $this->clientId = $clientId;
    }

    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new InvalidArgumentException(sprintf('The given AMQP DSN "%s" is invalid.', $dsn));
        }

        $this->serializer = $serializer;

        $pathParts = isset($parsedUrl['path']) ? explode('/', trim($parsedUrl['path'], '/')) : array();

        $credentials = array_replace_recursive(array(
            'host' => $parsedUrl['host'] ?? 'localhost',
            'port' => $parsedUrl['port'] ?? 1883,
            'client_id' => $this->clientId ?? getmypid(),
            'vhost' => isset($pathParts[0]) ? urldecode($pathParts[0]) : '/',
        ), $options);


        if (isset($parsedUrl['user'])) {
            $credentials['login'] = $parsedUrl['user'];
        }
        if (isset($parsedUrl['pass'])) {
            $credentials['password'] = $parsedUrl['pass'];
        }

        return new MqttTransport($credentials, $this->topics, $this->serializer);
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === strpos($dsn, 'mqtt://');
    }
}
