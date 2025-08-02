<?php

namespace PejmanAslani\Asterisk\Agi\Command;



readonly class GetVariableCommand implements CommandInterface {
    public function __construct(private string $name) {}
    public function asString(): string { return "GET VARIABLE {$this->name}"; }
}
