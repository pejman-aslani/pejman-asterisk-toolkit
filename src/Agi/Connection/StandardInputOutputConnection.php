<?php

declare(strict_types=1);

namespace PejmanAslani\Asterisk\Agi\Connection;

class StandardInputOutputConnection implements ConnectionInterface
{
    private $stdin;
    private $stdout;

    public function __construct()
    {
        // In a real AGI environment, PHP makes these available.
        // We use 'php://' streams for this.
        $this->stdin = fopen('php://stdin', 'r');
        $this->stdout = fopen('php://stdout', 'w');
    }

    public function readLine(): string
    {
        $line = fgets($this->stdin);
        // Return an empty string if reading fails (e.g., channel hangs up)
        return $line === false ? '' : rtrim($line);
    }

    public function writeLine(string $command): void
    {
        fwrite($this->stdout, $command . "\n");
        fflush($this->stdout); // Ensure the command is sent immediately
    }

    public function __destruct()
    {
        fclose($this->stdin);
        fclose($this->stdout);
    }
}