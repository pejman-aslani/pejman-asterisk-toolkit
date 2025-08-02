<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Agi\Command;

class AnswerCommand implements CommandInterface
{
    public function asString(): string
    {
        return 'ANSWER';
    }
}