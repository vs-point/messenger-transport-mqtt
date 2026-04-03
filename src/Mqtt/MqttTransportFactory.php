<?php

declare(strict_types=1);

namespace VSPoint\Messenger\Transport\Mqtt;

use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * @implements TransportFactoryInterface<MqttTransport>
 */
class MqttTransportFactory implements TransportFactoryInterface
{
    /**
     * @param string[] $topics
     */
    public function __construct(
        private readonly array $topics,
        private readonly ?string $clientId = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createTransport(string $dsn, array $options, SerializerInterface $serializer): TransportInterface
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new \InvalidArgumentException(sprintf('The given MQTT DSN "%s" is invalid.', $dsn));
        }

        $pathParts = isset($parsedUrl['path']) ? explode('/', trim($parsedUrl['path'], '/')) : [];

        /** @var array<string, mixed> $credentials */
        $credentials = array_replace_recursive([
            'host' => $parsedUrl['host'] ?? 'localhost',
            'port' => $parsedUrl['port'] ?? 1883,
            'client_id' => $this->clientId ?? (string) getmypid(),
            'vhost' => isset($pathParts[0]) ? urldecode($pathParts[0]) : '/',
            'login' => '',
            'password' => '',
        ], $options);

        if (isset($parsedUrl['user'])) {
            $credentials['login'] = $parsedUrl['user'];
        }

        if (isset($parsedUrl['pass'])) {
            $credentials['password'] = $parsedUrl['pass'];
        }

        return new MqttTransport($credentials, $this->topics);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function supports(string $dsn, array $options): bool
    {
        return str_starts_with($dsn, 'mqtt://');
    }
}
