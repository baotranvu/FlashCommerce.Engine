<?php

namespace App\Contracts\Exceptions;

interface HasProblemDetailsInterface
{
    public function getStatusCode(): int;
    public function getErrorCode(): string;
    public function getTitle(): string;
    public function getDetail(): string;

    /**
     * Optional HTTP headers (e.g., ['Retry-After' => '45']).
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Optional RFC 7807 extension attributes (e.g., ['retry_after_seconds' => 45]).
     *
     * @return array<string, mixed>
     */
    public function getExtensions(): array;
}
