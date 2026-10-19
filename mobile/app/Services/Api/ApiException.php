<?php

namespace App\Services\Api;

use RuntimeException;

/** A failed call to the HR backend, already turned into something safe to show to the user. */
class ApiException extends RuntimeException
{
    public const NO_CONNECTION = 0;

    /**
     * @param  int  $status  HTTP status, or 0 when the server could not be reached at all
     * @param  array<string, list<string>|string>  $errors  field => messages (HTTP 422)
     */
    public function __construct(string $message, public readonly int $status = self::NO_CONNECTION, public readonly array $errors = [])
    {
        parent::__construct($message, $status);
    }

    public function isUnauthenticated(): bool
    {
        return $this->status === 401;
    }

    public function isValidation(): bool
    {
        return $this->status === 422;
    }

    public function isConnection(): bool
    {
        return $this->status === self::NO_CONNECTION;
    }

    /** The first message for each field, e.g. ['email' => 'The provided credentials are incorrect.']. */
    public function fieldMessages(): array
    {
        $out = [];
        foreach ($this->errors as $field => $messages) {
            $out[$field] = is_array($messages) ? (string) ($messages[0] ?? '') : (string) $messages;
        }

        return $out;
    }
}
