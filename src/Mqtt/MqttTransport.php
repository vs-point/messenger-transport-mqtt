<?php

declare(strict_types=1);

namespace VSPoint\Messenger\Transport\Mqtt;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MqttTransport implements TransportInterface
{
    private MqttClient|null $client = null;

    private bool $initialized = false;

    /**
     * @var Envelope[]
     */
    private array $pendingEnvelopes = [];

    /**
     * @param array<string, mixed> $credentials
     * @param string[] $topics
     */
    public function __construct(
        private readonly array $credentials,
        private readonly array $topics,
    ) {
    }

    public function __destruct()
    {
        if ($this->client !== null && $this->client->isConnected()) {
            $this->client->disconnect();
        }
    }

    public function get(): iterable
    {
        $this->initialize();
        $this->loopUntilDeadline(0.1);

        $envelopes = $this->pendingEnvelopes;
        $this->pendingEnvelopes = [];

        return $envelopes;
    }

    public function ack(Envelope $envelope): void
    {
        // MQTT handles acknowledgment at protocol level via QoS
    }

    public function reject(Envelope $envelope): void
    {
        // MQTT does not support message rejection
    }

    public function send(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();

        if ($message instanceof MqttMessageInterface) {
            $this->connectClient();
            $this->client->publish($message->getTopic(), $message->getBody(), $message->getQos());

            if ($message->getQos() > MqttClient::QOS_AT_MOST_ONCE) {
                $this->loopUntilDeadline(0.3);
            }
        }

        return $envelope;
    }

    private function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->connectClient();

        foreach ($this->topics as $topic) {
            $this->client->subscribe(
                $topic,
                function (string $topic, string $message): void {
                    $this->pendingEnvelopes[] = new Envelope(
                        new MqttMessage($topic, 0, $message, uniqid('mqtt_', true))
                    );
                },
                MqttClient::QOS_AT_MOST_ONCE,
            );
        }

        $this->initialized = true;
    }

    private function loopUntilDeadline(float $seconds): void
    {
        $deadline = microtime(true) + $seconds;
        $handler = function (MqttClient $client, float $elapsedTime) use ($deadline): void {
            if (microtime(true) >= $deadline) {
                $client->interrupt();
            }
        };

        $this->client->registerLoopEventHandler($handler);
        $this->client->loop(allowSleep: false);
        $this->client->unregisterLoopEventHandler($handler);
    }

    private function connectClient(): void
    {
        if ($this->client === null) {
            $this->client = new MqttClient(
                $this->credentials['host'],
                $this->credentials['port'],
                $this->credentials['client_id'],
            );
        }

        if (!$this->client->isConnected()) {
            $settings = (new ConnectionSettings())
                ->setReconnectAutomatically(true);

            if ($this->credentials['login'] !== '') {
                $settings = $settings
                    ->setUsername($this->credentials['login'])
                    ->setPassword($this->credentials['password']);
            }

            $this->client->connect($settings);
        }
    }
}
