<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Agi\Command;

readonly class StreamFileCommand implements CommandInterface
{
    /**
     * @param string $filename The sound file to play (without extension).
     * @param string $escapeDigits Digits that can interrupt the playback.
     */
    public function __construct(
        private string $filename,
        private string $escapeDigits = ''
    ) {}

    public function asString(): string
    {
        // Asterisk requires the escape digits to be enclosed in double quotes.
        return "STREAM FILE {$this->filename} \"{$this->escapeDigits}\"";
    }
}