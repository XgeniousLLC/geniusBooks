<?php

namespace App\Services\Sms\Drivers;

use App\Services\Sms\Contracts\SmsDriver;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Posts to any HTTP SMS gateway (SSLWireless, BulkSMSBD, GreenWeb, etc.).
 * The URL may contain {to}, {message} and {from} placeholders; extra static
 * parameters can be supplied via the driver config.
 */
class HttpSmsDriver implements SmsDriver
{
    /**
     * @param  array<string, string>  $params
     */
    public function __construct(
        private readonly ?string $url,
        private readonly string $method = 'POST',
        private readonly ?string $token = null,
        private readonly array $params = [],
    ) {}

    public function send(string $to, string $message, ?string $from = null): void
    {
        if (! $this->url) {
            throw new RuntimeException('The SMS HTTP gateway URL is not configured.');
        }

        $url = strtr($this->url, [
            '{to}' => rawurlencode($to),
            '{message}' => rawurlencode($message),
            '{from}' => rawurlencode((string) $from),
        ]);

        $payload = array_merge($this->params, [
            'to' => $to,
            'message' => $message,
            'sender' => $from,
        ]);

        $request = Http::asForm();

        if ($this->token) {
            $request = $request->withToken($this->token);
        }

        $response = strtoupper($this->method) === 'GET'
            ? $request->get($url, $payload)
            : $request->post($url, $payload);

        $response->throw();
    }
}
