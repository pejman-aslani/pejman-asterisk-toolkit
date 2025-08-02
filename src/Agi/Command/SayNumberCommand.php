<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Agi\Command;

use Pejman\Asterisk\Agi\Command\CommandInterface;

/**
 * Represents the 'SAY NUMBER' AGI command.
 * This command says the given number, allowing interruption by the specified escape digits.
 */
readonly class SayNumberCommand implements CommandInterface
{
    /**
     * @param int $number The number to say.
     * @param string $escapeDigits A string of digits that, if pressed, will interrupt the playback.
     */
    public function __construct(
        private int    $number,
        private string $escapeDigits = ''
    ) {}

    /**
     * Builds the AGI command string.
     * e.g., "SAY NUMBER 123 "123#""
     * @return string
     */
    public function asString(): string
    {
        // The AGI specification requires the escape digits to be enclosed in double quotes.
        return "SAY NUMBER {$this->number} \"{$this->escapeDigits}\"";
    }
}