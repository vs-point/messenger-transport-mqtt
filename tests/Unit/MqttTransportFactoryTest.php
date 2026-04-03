<?php

declare(strict_types=1);

namespace VSPoint\Messenger\Transport\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use VSPoint\Messenger\Transport\Mqtt\MqttTransport;
use VSPoint\Messenger\Transport\Mqtt\MqttTransportFactory;

final class MqttTransportFactoryTest extends TestCase
{
    public function testSupportsReturnsTrueForMqttDsn(): void
    {
        $factory = new MqttTransportFactory(['/topic']);

        self::assertTrue($factory->supports('mqtt://localhost:1883', []));
        self::assertTrue($factory->supports('mqtt://user:pass@broker.example.com:1883', []));
        self::assertTrue($factory->supports('mqtt://localhost', []));
    }

    public function testSupportsReturnsFalseForOtherSchemes(): void
    {
        $factory = new MqttTransportFactory(['/topic']);

        self::assertFalse($factory->supports('amqp://localhost', []));
        self::assertFalse($factory->supports('redis://localhost', []));
        self::assertFalse($factory->supports('sync://', []));
        self::assertFalse($factory->supports('', []));
    }

    public function testCreateTransportReturnsMqttTransportInstance(): void
    {
        $factory = new MqttTransportFactory(['/topic'], 'test-client');

        $transport = $factory->createTransport('mqtt://localhost:1883', [], $this->mockSerializer());

        self::assertInstanceOf(MqttTransport::class, $transport);
    }

    public function testCreateTransportThrowsOnInvalidDsn(): void
    {
        $factory = new MqttTransportFactory([]);

        $this->expectException(\InvalidArgumentException::class);

        $factory->createTransport(':', [], $this->mockSerializer());
    }

    public function testCreateTransportParsesHostAndPortFromDsn(): void
    {
        $factory = new MqttTransportFactory([], 'client');

        $transport = $factory->createTransport('mqtt://broker.example.com:1884', [], $this->mockSerializer());

        $credentials = $this->credentials($transport);
        self::assertSame('broker.example.com', $credentials['host']);
        self::assertSame(1884, $credentials['port']);
    }

    public function testCreateTransportDefaultsToPort1883(): void
    {
        $factory = new MqttTransportFactory([], 'client');

        $transport = $factory->createTransport('mqtt://localhost', [], $this->mockSerializer());

        self::assertSame(1883, $this->credentials($transport)['port']);
    }

    public function testCreateTransportParsesCredentialsFromDsn(): void
    {
        $factory = new MqttTransportFactory([], 'client');

        $transport = $factory->createTransport('mqtt://myuser:mypass@localhost:1883', [], $this->mockSerializer());

        $credentials = $this->credentials($transport);
        self::assertSame('myuser', $credentials['login']);
        self::assertSame('mypass', $credentials['password']);
    }

    public function testCreateTransportUsesClientIdFromConstructor(): void
    {
        $factory = new MqttTransportFactory([], 'my-fixed-client-id');

        $transport = $factory->createTransport('mqtt://localhost:1883', [], $this->mockSerializer());

        self::assertSame('my-fixed-client-id', $this->credentials($transport)['client_id']);
    }

    public function testCreateTransportFallsBackToProcessIdWhenClientIdIsNull(): void
    {
        $factory = new MqttTransportFactory([]);

        $transport = $factory->createTransport('mqtt://localhost:1883', [], $this->mockSerializer());

        self::assertSame((string) getmypid(), $this->credentials($transport)['client_id']);
    }

    public function testCreateTransportOptionsOverrideDefaults(): void
    {
        $factory = new MqttTransportFactory([], 'client');

        $transport = $factory->createTransport(
            'mqtt://localhost:1883',
            ['host' => 'override.example.com', 'port' => 9999],
            $this->mockSerializer(),
        );

        $credentials = $this->credentials($transport);
        self::assertSame('override.example.com', $credentials['host']);
        self::assertSame(9999, $credentials['port']);
    }

    /**
     * @return array<string, mixed>
     */
    private function credentials(MqttTransport $transport): array
    {
        $ref = new \ReflectionProperty(MqttTransport::class, 'credentials');

        return $ref->getValue($transport);
    }

    private function mockSerializer(): SerializerInterface
    {
        return $this->createMock(SerializerInterface::class);
    }
}
