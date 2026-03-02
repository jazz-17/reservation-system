<?php

namespace App\Settings\Exceptions;

use RuntimeException;

class InvalidSettingsException extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'Invalid application settings.',
    ) {
        parent::__construct($message);
    }
}
