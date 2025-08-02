<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Agi\Command;

readonly class GetDataCommand implements CommandInterface
{
    /**
     * @param string $filename The sound file to play.
     * @param int $timeout The timeout in milliseconds to wait for input.
     * @param int $maxDigits The maximum number of digits to accept.
     */
    public function __construct(
        private string $filename,
        private int    $timeout = 5000, // 5 seconds default
        private int    $maxDigits = 10,
    ) {}

    public function asString(): string
    {
        return "GET DATA {$this->filename} {$this->timeout} {$this->maxDigits}";
    }
}