<?php

namespace VSPoint\Messenger\Transport\Mqtt;

class MqttMessage implements MqttMessageInterface
{
    public function __construct(string $topic, int $qos, string $body, string $id)
    {
        $this->topic = $topic;
        $this->qos = $qos;
        $this->body = $body;
        $this->id = $id;
    }

    private $qos;
    private $body;
    private $topic;
    private $id;

    public function getTopic() : string {
        return $this->topic;
    }
    public function getQos() : int {
        return $this->qos;
    }
    public function getBody() : string {
        return $this->body;
    }
    public function getId() : string {
        return $this->id;
    }
}