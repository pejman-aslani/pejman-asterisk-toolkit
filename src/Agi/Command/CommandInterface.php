<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Agi\Command;

interface CommandInterface
{
    /**
     * Returns the command in the string format that Asterisk understands.
     * e.g., "STREAM FILE welcome 12345"
     */
    public function asString(): string;
}