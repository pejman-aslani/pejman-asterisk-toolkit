<?php

declare(strict_types=1);

namespace PejmanAslani\Asterisk\Agi\Command;

readonly class ExecCommand implements CommandInterface
{
    /**
     * The options for the application.
     * We declare the property manually here.
     * @var string[]
     */
    private array $options;

    public function __construct(
        // We can still promote the non-variadic property.
        private string $application,
        
        // This is now a regular variadic parameter without promotion.
        string ...$options
    ) {
        // We manually assign the variadic arguments (which is an array) to our property.
        $this->options = $options;
    }

    public function asString(): string
    {
        // The rest of the logic is correct.
        // It correctly uses $this->options which is an array.
        if (empty($this->options)) {
            return "EXEC {$this->application}";
        }
        
        $optionsString = '"' . implode(',', $this->options) . '"';
        return "EXEC {$this->application} {$optionsString}";
    }
}