<?php

namespace App\Settings\Exceptions;

use RuntimeException;

class MissingSettingsException extends RuntimeException
{
    /**
     * @param  list<string>  $missingKeys
     */
    public function __construct(
        public readonly array $missingKeys,
        string $message = 'Missing required application settings.',
    ) {
        parent::__construct($message);
    }
}
