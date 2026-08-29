<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\Http;

final class HttpException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $status = null,
        private readonly bool $retryable = false,
        private readonly bool $ambiguous = false
    ) {
        parent::__construct($message);
    }

    public function status(): ?int
    {
        return $this->status;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function isAmbiguous(): bool
    {
        return $this->ambiguous;
    }
}
