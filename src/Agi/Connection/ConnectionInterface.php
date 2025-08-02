<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Agi\Connection;

interface ConnectionInterface
{
    /**
     * Reads a single line from the Asterisk server.
     */
    public function readLine(): string;

    /**
     * Writes a single line (command) to the Asterisk server.
     */
    public function writeLine(string $command): void;
}