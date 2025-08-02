<?php

namespace Pejman\Asterisk\Agi\Command;
readonly class DatabaseGetCommand implements CommandInterface {
    public function __construct(private string $family, private string $key) {}
    public function asString(): string { return "DATABASE GET {$this->family} {$this->key}"; }
}