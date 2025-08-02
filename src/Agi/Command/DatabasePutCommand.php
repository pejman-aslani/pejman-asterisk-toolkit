<?php

namespace PejmanAslani\Asterisk\Agi\Command;
readonly class DatabasePutCommand implements CommandInterface {
    public function __construct(private string $family, private string $key, private string $value) {}
    public function asString(): string { return "DATABASE PUT {$this->family} {$this->key} {$this->value}"; }
}