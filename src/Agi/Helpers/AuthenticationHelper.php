<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Agi\Helpers;

use Pejman\Asterisk\Agi\AGI;
use LogicException;

class AuthenticationHelper
{
    private string $promptFile = '';
    private int $attempts = 3;
    private int $pinLength = 4;

    private ?\Closure $validator = null;
    private ?\Closure $successCallback = null;
    private ?\Closure $failureCallback = null;
    private ?\Closure $maxAttemptsCallback = null;

    public function __construct(private AGI $agi) {}

    /**
     * Sets the prompt file to ask for the PIN.
     */
    public function prompt(string $filename): self
    {
        $this->promptFile = $filename;
        return $this;
    }

    /**
     * Sets the maximum number of attempts.
     */
    public function maxAttempts(int $count): self
    {
        $this->attempts = $count;
        return $this;
    }

    /**
     * Sets the expected PIN length.
     */
    public function pinLength(int $length): self
    {
        $this->pinLength = $length;
        return $this;
    }

    /**
     * Sets the validation logic. The callback must accept a string (the PIN)
     * and return a boolean (true for valid, false for invalid).
     */
    public function validator(\Closure $callback): self
    {
        $this->validator = $callback;
        return $this;
    }

    /**
     * Sets the callback to run on successful authentication.
     */
    public function onSuccess(\Closure $callback): self
    {
        $this->successCallback = $callback;
        return $this;
    }

    /**
     * Sets the callback to run on each failed attempt.
     * The callback will receive the number of attempts remaining.
     */
    public function onFailure(\Closure $callback): self
    {
        $this->failureCallback = $callback;
        return $this;
    }

    /**
     * Sets the callback to run when the maximum number of attempts is reached.
     */
    public function onMaxAttempts(\Closure $callback): self
    {
        $this->maxAttemptsCallback = $callback;
        return $this;
    }

    /**
     * Executes the authentication flow.
     * @return bool True if authentication was successful, false otherwise.
     */
    public function execute(): bool
    {
        if (!$this->validator || !$this->promptFile) {
            throw new LogicException('Validator and prompt file must be set before executing authentication.');
        }

        for ($i = 1; $i <= $this->attempts; $i++) {
            $response = $this->agi->getData($this->promptFile, 7000, $this->pinLength);
            $pin = (string)$response->getResult();

            // Use the user-provided validator function
            if ($response->isSuccess() && ($this->validator)($pin)) {
                if ($this->successCallback) {
                    ($this->successCallback)($pin, $this->agi);
                }
                return true; // Authentication successful
            }
            
            // Handle failure on this attempt
            $attemptsLeft = $this->attempts - $i;
            if ($attemptsLeft > 0) {
                if ($this->failureCallback) {
                    ($this->failureCallback)($attemptsLeft, $this->agi);
                }
            }
        }
        
        // If the loop finishes, all attempts have failed
        if ($this->maxAttemptsCallback) {
            ($this->maxAttemptsCallback)($this->agi);
        }
        
        return false; // Authentication failed
    }
}