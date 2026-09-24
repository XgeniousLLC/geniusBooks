<?php

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsDriver;
use App\Services\Sms\Drivers\FakeSmsDriver;
use App\Services\Sms\Drivers\HttpSmsDriver;
use App\Services\Sms\Drivers\LogSmsDriver;
use App\Services\Sms\Drivers\NullSmsDriver;
use App\Services\Sms\Drivers\TwilioSmsDriver;
use App\Services\Sms\Drivers\VonageSmsDriver;
use RuntimeException;

/**
 * Resolves the configured SMS driver and sends messages.
 *
 * Configure the platform driver via config/sms.php (env SMS_DRIVER). Companies
 * opt in to SMS notifications with their own toggle; this layer only handles
 * delivery.
 */
class SmsManager
{
    private ?SmsDriver $driver = null;

    public function driver(?string $name = null): SmsDriver
    {
        if ($this->driver !== null && $name === null) {
            return $this->driver;
        }

        $name ??= config('sms.driver', 'log');
        $config = config("sms.drivers.{$name}");

        if (! is_array($config)) {
            throw new RuntimeException("SMS driver [{$name}] is not configured.");
        }

        return $this->make($config);
    }

    public function send(string $to, string $message, ?string $from = null): void
    {
        $to = trim($to);

        if ($to === '' || trim($message) === '') {
            return;
        }

        $this->driver()->send($to, $message, $from ?: config('sms.from'));
    }

    public function setDriver(SmsDriver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Swap in a recording driver for tests.
     */
    public static function fake(): FakeSmsDriver
    {
        $fake = new FakeSmsDriver;
        app(SmsManager::class)->setDriver($fake);

        return $fake;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function make(array $config): SmsDriver
    {
        return match ($config['driver'] ?? 'log') {
            'log' => new LogSmsDriver,
            'null' => new NullSmsDriver,
            'twilio' => new TwilioSmsDriver($config['sid'] ?? null, $config['token'] ?? null, $config['from'] ?? null),
            'vonage' => new VonageSmsDriver($config['key'] ?? null, $config['secret'] ?? null, $config['from'] ?? null),
            'http' => new HttpSmsDriver(
                $config['url'] ?? null,
                $config['method'] ?? 'POST',
                $config['token'] ?? null,
                $config['params'] ?? [],
            ),
            default => throw new RuntimeException("Unsupported SMS driver [{$config['driver']}]."),
        };
    }
}
