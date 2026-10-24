<?php

namespace FlashCommerce\Identity\Domain\Exceptions;

use App\Contracts\Exceptions\HasProblemDetailsInterface;
use DomainException;

abstract class IdentityDomainException extends DomainException implements HasProblemDetailsInterface
{
    abstract public function getStatusCode(): int;
    abstract public function getErrorCode(): string;
    abstract public function getTitle(): string;
    abstract public function getDetail(): string;

    public function getHeaders(): array
    {
        return [];
    }

    public function getExtensions(): array
    {
        return [];
    }
}
