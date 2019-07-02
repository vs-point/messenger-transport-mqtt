<?php

namespace VSPoint\Messenger\Transport\Mqtt;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MqttTransport implements TransportInterface
{
    private $credentials;

    /** @var \Mosquitto\Client */
    private $client;

    /** @var bool */
    private $connected;

    /** @var bool */
    private $shouldStop;

    /** @var array */
    private $topics;

    public function __construct(array $credentials, array $topics)
    {
        $this->credentials = $credentials;
        $this->connected = false;
        $this->topics = $topics;
    }

    public function send(Envelope $envelope): Envelope
    {
        if($envelope->getMessage() instanceof MqttMessageInterface) {
            $this->connect();
            $this->client->publish($envelope->getMessage()->getTopic(), $envelope->getMessage()->getBody(), $envelope->getMessage()->getQos());
            $this->client->loop();
        }
        return $envelope;
    }

    public function receive(callable $handler): void
    {
        $this->client->onMessage(function($message) use ($handler) {
            $handler(new MqttMessage($message->topic,$message->qos,$message->payload,$message->mid));
        });
        $this->subscribe();
        $this->client->loopForever();
    }

    public function stop(): void
    {
        $this->client->exitLoop();
        if(isset($this->client)) {
            $this->client->disconnect();
        }
    }

    /**
     * Creates new instance of a MQTT client
     *
     * @return \Mosquitto\Client
     */
    private function createClient(): \Mosquitto\Client
    {
        $client = new \Mosquitto\Client($this->credentials['client_id'],false);
        $client->setCredentials($this->credentials['login'], $this->credentials['password']);
        $client->onDisconnect(function(){
            $this->connected = false;
        });

        return $client;
    }

    private function connect() {
        if(!isset($this->client)) {
            $this->client = $this->createClient();
        }
        if($this->connected == false) {
            $this->client->connect($this->credentials['host'],$this->credentials['port']);
            $this->connected = true;
        }
    }

    private function subscribe() {

        $this->connect();
        foreach ($this->topics as $topic) {
            $this->client->subscribe($topic, 0);
        }
    }
}
