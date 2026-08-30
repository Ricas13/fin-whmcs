<?php

declare(strict_types=1);

namespace CaptainFin\Whmcs\Integrations\MediaServer;

class MediaServerException extends \RuntimeException
{
    private ?int $statusCode;
    private bool $retryable;
    private bool $ambiguous;

    public function __construct(
        string $message,
        ?int $statusCode = null,
        bool $retryable = false,
        bool $ambiguous = false,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->statusCode = $statusCode;
        $this->retryable = $retryable;
        $this->ambiguous = $ambiguous;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    public function isRetryable(): bool
    {
        return $this->retryable;
    }

    public function isAmbiguous(): bool
    {
        return $this->ambiguous;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }
}
