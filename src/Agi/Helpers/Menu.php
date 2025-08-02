<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Agi\Helpers;

use Pejman\Asterisk\Agi\AGI;

/**
 * A Menu Builder helper to create interactive menus with a fluent interface.
 */
class Menu
{
    private string $promptFile = '';
    private array $options = [];
    private ?\Closure $invalidCallback = null;
    private ?\Closure $timeoutCallback = null;
    private int $timeout = 5000;
    private int $maxDigits = 1;

    public function __construct(private AGI $agi) {}

    /**
     * Sets the sound file to be played as the menu prompt.
     */
    public function prompt(string $filename): self
    {
        $this->promptFile = $filename;
        return $this;
    }

    /**
     * Associates a digit with a callback function to be executed.
     */
    public function option(string $digit, \Closure $callback): self
    {
        $this->options[$digit] = $callback;
        return $this;
    }

    /**
     * Sets the callback for when the user enters an invalid option.
     */
    public function onInvalid(\Closure $callback): self
    {
        $this->invalidCallback = $callback;
        return $this;
    }

    /**
     * Sets the callback for when the user input times out.
     * If not set, onInvalid will be used as a fallback.
     */
    public function onTimeout(\Closure $callback): self
    {
        $this->timeoutCallback = $callback;
        return $this;
    }

    /**
     * Sets the prompt timeout and max digits.
     */
    public function withConfig(int $timeout, int $maxDigits): self
    {
        $this->timeout = $timeout;
        $this->maxDigits = $maxDigits;
        return $this;
    }

    /**
     * Executes the menu logic.
     */
    public function execute(): void
    {
        if (empty($this->promptFile)) {
            throw new \LogicException('Cannot execute a menu without a prompt file.');
        }

        $response = $this->agi->getData($this->promptFile, $this->timeout, $this->maxDigits);

        // Check for hangup or failure first
        if (!$response->isSuccess() || $response->getResult() === -1) {
            $this->handleTimeout();
            return;
        }

        $choice = chr($response->getResult());

        if (isset($this->options[$choice])) {
            // Execute the callback for the chosen option
            $this->options[$choice]($this->agi);
        } else {
            // Handle invalid input
            $this->handleInvalid();
        }
    }

    private function handleInvalid(): void
    {
        if ($this->invalidCallback) {
            ($this->invalidCallback)($this->agi);
        }
    }


    private function handleTimeout(): void
    {
        // Use the specific timeout callback if it exists, otherwise fall back to invalid.
        $callback = $this->timeoutCallback ?? $this->invalidCallback;
        if ($callback) {
            $callback($this->agi);
        }
    }
}