<?php

declare(strict_types=1);

namespace VSPoint\Messenger\Transport\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use VSPoint\Messenger\Transport\Mqtt\MqttMessage;
use VSPoint\Messenger\Transport\Mqtt\MqttTransport;
use VSPoint\Messenger\Transport\Tests\Integration\Fixture\TestMessage;

final class MqttTransportTest extends TestCase
{
    private string $host;

    private int $port;

    protected function setUp(): void
    {
        $this->host = (string) (getenv('MQTT_HOST') ?: 'mqtt');
        $this->port = (int) (getenv('MQTT_PORT') ?: 1883);

        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, 3);

        if ($socket === false) {
            self::markTestSkipped(
                sprintf('MQTT broker not available at %s:%d — %s', $this->host, $this->port, $errstr)
            );
        }

        fclose($socket);
    }

    public function testQos0MessageIsDeliveredAndReceived(): void
    {
        $topic = '/test/transport/' . uniqid();

        $subscriber = $this->createTransport('sub-' . uniqid(), [$topic]);
        $publisher = $this->createTransport('pub-' . uniqid(), []);

        // Initialize subscription (connect + subscribe + first poll)
        $subscriber->get();

        $publisher->send(new Envelope(new TestMessage($topic, 0, 'hello-qos0')));

        $envelopes = $this->waitForEnvelopes($subscriber, 1);

        self::assertCount(1, $envelopes);

        $received = $envelopes[0]->getMessage();
        self::assertInstanceOf(MqttMessage::class, $received);
        self::assertSame($topic, $received->getTopic());
        self::assertSame('hello-qos0', $received->getBody());
        self::assertSame(0, $received->getQos());
    }

    public function testQos1MessageIsDeliveredAndReceived(): void
    {
        $topic = '/test/transport/' . uniqid();

        $subscriber = $this->createTransport('sub-' . uniqid(), [$topic]);
        $publisher = $this->createTransport('pub-' . uniqid(), []);

        $subscriber->get();

        $publisher->send(new Envelope(new TestMessage($topic, 1, 'hello-qos1')));

        $envelopes = $this->waitForEnvelopes($subscriber, 1);

        self::assertCount(1, $envelopes);

        $received = $envelopes[0]->getMessage();
        self::assertInstanceOf(MqttMessage::class, $received);
        self::assertSame($topic, $received->getTopic());
        self::assertSame('hello-qos1', $received->getBody());
    }

    public function testMultipleMessagesAreDeliveredInOrder(): void
    {
        $topic = '/test/transport/' . uniqid();

        $subscriber = $this->createTransport('sub-' . uniqid(), [$topic]);
        $publisher = $this->createTransport('pub-' . uniqid(), []);

        $subscriber->get();

        $bodies = ['first', 'second', 'third'];

        foreach ($bodies as $body) {
            $publisher->send(new Envelope(new TestMessage($topic, 0, $body)));
        }

        $envelopes = $this->waitForEnvelopes($subscriber, 3);

        self::assertCount(3, $envelopes);

        $receivedBodies = array_map(
            static fn (Envelope $e) => $e->getMessage()->getBody(),
            $envelopes,
        );

        self::assertSame($bodies, $receivedBodies);
    }

    public function testMessagesFromMultipleTopicsAreReceived(): void
    {
        $topicA = '/test/transport/a/' . uniqid();
        $topicB = '/test/transport/b/' . uniqid();

        $subscriber = $this->createTransport('sub-' . uniqid(), [$topicA, $topicB]);
        $publisher = $this->createTransport('pub-' . uniqid(), []);

        $subscriber->get();

        $publisher->send(new Envelope(new TestMessage($topicA, 0, 'from-a')));
        $publisher->send(new Envelope(new TestMessage($topicB, 0, 'from-b')));

        $envelopes = $this->waitForEnvelopes($subscriber, 2);

        self::assertCount(2, $envelopes);

        $topics = array_map(
            static fn (Envelope $e) => $e->getMessage()->getTopic(),
            $envelopes,
        );

        self::assertContains($topicA, $topics);
        self::assertContains($topicB, $topics);
    }

    public function testWildcardTopicReceivesMatchingMessages(): void
    {
        $prefix = '/test/wildcard/' . uniqid();

        $subscriber = $this->createTransport('sub-' . uniqid(), [$prefix . '/#']);
        $publisher = $this->createTransport('pub-' . uniqid(), []);

        $subscriber->get();

        $publisher->send(new Envelope(new TestMessage($prefix . '/a', 0, 'msg-a')));
        $publisher->send(new Envelope(new TestMessage($prefix . '/b', 0, 'msg-b')));
        $publisher->send(new Envelope(new TestMessage($prefix . '/deep/nested', 0, 'msg-deep')));

        $envelopes = $this->waitForEnvelopes($subscriber, 3);

        self::assertCount(3, $envelopes);

        $topics = array_map(
            static fn (Envelope $e) => $e->getMessage()->getTopic(),
            $envelopes,
        );

        self::assertContains($prefix . '/a', $topics);
        self::assertContains($prefix . '/b', $topics);
        self::assertContains($prefix . '/deep/nested', $topics);
    }

    public function testNonMqttMessageIsNotPublished(): void
    {
        $topic = '/test/transport/' . uniqid();

        $subscriber = $this->createTransport('sub-' . uniqid(), [$topic]);
        $publisher = $this->createTransport('pub-' . uniqid(), []);

        $subscriber->get();

        $sentEnvelope = new Envelope(new \stdClass());
        $returnedEnvelope = $publisher->send($sentEnvelope);

        // send() must return the envelope unchanged
        self::assertSame($sentEnvelope, $returnedEnvelope);

        // nothing should arrive on the broker
        $envelopes = $this->waitForEnvelopes($subscriber, 1, 3);
        self::assertCount(0, $envelopes);
    }

    public function testAckAndRejectDoNotThrow(): void
    {
        $topic = '/test/transport/' . uniqid();

        $transport = $this->createTransport('sub-' . uniqid(), [$topic]);
        $publisher = $this->createTransport('pub-' . uniqid(), []);

        $transport->get();

        $publisher->send(new Envelope(new TestMessage($topic, 0, 'ack-test')));

        $envelopes = $this->waitForEnvelopes($transport, 1);
        self::assertCount(1, $envelopes);

        // ack and reject must not throw for MQTT (protocol handles it via QoS)
        $transport->ack($envelopes[0]);
        $transport->reject($envelopes[0]);

        $this->addToAssertionCount(1);
    }

    /**
     * @param string[] $topics
     */
    private function createTransport(string $clientId, array $topics): MqttTransport
    {
        return new MqttTransport(
            [
                'host' => $this->host,
                'port' => $this->port,
                'client_id' => $clientId,
                'login' => '',
                'password' => '',
            ],
            $topics,
        );
    }

    /**
     * @return Envelope[]
     */
    private function waitForEnvelopes(MqttTransport $transport, int $expectedCount, int $maxRetries = 20): array
    {
        $all = [];

        for ($i = 0; $i < $maxRetries && count($all) < $expectedCount; $i++) {
            foreach ($transport->get() as $envelope) {
                $all[] = $envelope;
            }
        }

        return $all;
    }
}
