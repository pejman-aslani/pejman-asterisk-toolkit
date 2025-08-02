<?php


namespace Pejman\Asterisk\Agi\Command;
readonly class SetVariableCommand implements CommandInterface {
    public function __construct(private string $name, private string $value) {}
    public function asString(): string { return "SET VARIABLE {$this->name} \"{$this->value}\""; }
}