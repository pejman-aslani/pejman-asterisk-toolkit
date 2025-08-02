<?php

declare(strict_types=1);

namespace PejmanAslani\Asterisk\Agi\Connection;

use PejmanAslani\Asterisk\Agi\Exception\ConnectionException;

/**
 * Connection handler for FastAGI using network sockets.
 * This version includes improved error handling and explicit state management.
 */
class FastAgiConnection implements ConnectionInterface
{
    /**
     * The active socket connection resource.
     * @var resource|null
     */
    private mixed $socket;

    /**
     * @param resource $socket
     */
    public function __construct($socket)
    {
        if (!is_resource($socket) || !str_starts_with(get_resource_type($socket), 'stream')) {
            throw new \InvalidArgumentException('FastAgiConnection expects a valid stream socket resource.');
        }
        $this->socket = $socket;
    }

    public function readLine(): string
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Cannot read line: socket is not connected.');
        }

        $line = @fgets($this->socket); // Use @ to suppress warnings on connection failure

        if ($line === false || feof($this->socket)) {
            $this->close(); // Close the connection as it's likely dead
            throw new ConnectionException('Failed to read from socket or connection closed by peer.');
        }

        return rtrim($line);
    }

    public function writeLine(string $command): void
    {
        if (!$this->isConnected()) {
            throw new ConnectionException('Cannot write line: socket is not connected.');
        }

        $bytesWritten = @fwrite($this->socket, $command . "\n");

        if ($bytesWritten === false) {
            $this->close();
            throw new ConnectionException('Failed to write to socket.');
        }

        fflush($this->socket);
    }

    /**
     * Checks if the socket connection is active.
     */
    public function isConnected(): bool
    {
        return is_resource($this->socket) && !feof($this->socket);
    }

    /**
     * Explicitly closes the socket connection.
     */
    public function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
        }
    }

    /**
     * The destructor ensures the connection is closed when the object is destroyed.
     */
    public function __destruct()
    {
        $this->close();
    }
}