<?php

declare(strict_types=1);

namespace PejmanAslani\Asterisk\Agi;

/**
 * Represents a response from the Asterisk server for a given command.
 * It parses the raw response line like "200 result=1 (timeout)" into structured data.
 */
readonly class Response
{
    /**
     * @param int $code The 3-digit response code (e.g., 200 for success).
     * @param int|string $result The result value (e.g., 1, 0, -1, or ASCII code).
     * @param string|null $data Additional data, often in parentheses (e.g., "timeout").
     * @param string $rawLine The original, unparsed line from Asterisk.
     */
    public function __construct(
        public int        $code,
        public int|string $result,
        public ?string    $data = null,
        public string     $rawLine = '',
    ) {}

    /**
     * Factory method to create a Response object from a raw string line.
     */
    public static function fromString(string $line): self
    {
        // This regex captures the code, result, and optional data in parentheses.
        $pattern = '/^(\d{3}) result=(-?\d+|\d{4,})(?:\s\(?(.*?)\)?)?$/';

        if (preg_match($pattern, trim($line), $matches)) {
            $code = (int) $matches[1];
            $result = ctype_digit($matches[2]) ? (int) $matches[2] : $matches[2]; // Keep result as string if not a simple int
            $data = $matches[3] ?? null;

            return new self($code, $result, $data, $line);
        }

        // If the line doesn't match, it's likely an invalid or unexpected response.
        return new self(500, -1, 'Invalid response format', $line);
    }

    /**
     * Checks if the command was executed successfully.
     */
    public function isSuccess(): bool
    {
        return $this->code === 200;
    }
    
    /**
     * Gets the main result of the command.
     */
    public function getResult(): int|string
    {
        return $this->result;
    }

    /**
     * Gets the additional data from the response.
     */
    public function getData(): ?string
    {
        return $this->data;
    }
}