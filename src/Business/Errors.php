<?php

declare(strict_types=1);

namespace App\Business;

class MercadoError extends \RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 400)
    {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}

class ValidationError extends MercadoError
{
    public function __construct(string $message)
    {
        parent::__construct($message, 400);
    }
}

class NotFoundError extends MercadoError
{
    public function __construct(string $message)
    {
        parent::__construct($message, 404);
    }
}
