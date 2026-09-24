<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsDriver;
use PHPUnit\Framework\Assert;

/**
 * Records SMS in memory so tests can assert what would have been sent.
 */
class FakeSmsDriver implements SmsDriver
{
    /** @var list<array{to: string, message: string, from: ?string}> */
    public array $messages = [];

    public function send(string $to, string $message, ?string $from = null): void
    {
        $this->messages[] = ['to' => $to, 'message' => $message, 'from' => $from];
    }

    public function assertSent(callable $callback): void
    {
        foreach ($this->messages as $message) {
            if ($callback($message)) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail('No SMS matched the given filter.');
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->messages, 'Unexpected SMS were sent.');
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->messages);
    }
}
