<?php

declare(strict_types=1);

namespace PejmanAslani\Asterisk\Agi\Command;

class AnswerCommand implements CommandInterface
{
    public function asString(): string
    {
        return 'ANSWER';
    }
}