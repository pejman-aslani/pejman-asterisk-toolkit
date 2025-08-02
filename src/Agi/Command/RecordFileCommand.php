<?php


namespace PejmanAslani\Asterisk\Agi\Command;
readonly class RecordFileCommand implements CommandInterface {
    public function __construct(
        private string $filename,
        private string $format = 'gsm',
        private string $escapeDigits = '#',
        private int    $timeout = -1, // -1 means no timeout
    ) {}
    public function asString(): string {
        return "RECORD FILE {$this->filename} {$this->format} \"{$this->escapeDigits}\" {$this->timeout}";
    }
}