<?php

namespace PejmanAslani\Asterisk\Agi\Command;
readonly class GotoCommand implements CommandInterface {
    public function __construct(
        private string $context,
        private string $extension,
        private int    $priority = 1
    ) {}
    public function asString(): string { return "GOTO {$this->context} {$this->extension} {$this->priority}"; }
}