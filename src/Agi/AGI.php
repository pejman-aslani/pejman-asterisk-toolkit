<?php

declare(strict_types=1);

namespace PejmanAslani\Asterisk\Agi;

use PejmanAslani\Asterisk\Agi\Command\AnswerCommand;
use PejmanAslani\Asterisk\Agi\Command\CommandInterface;
use PejmanAslani\Asterisk\Agi\Command\DatabaseGetCommand;
use PejmanAslani\Asterisk\Agi\Command\DatabasePutCommand;
use PejmanAslani\Asterisk\Agi\Command\ExecCommand;
use PejmanAslani\Asterisk\Agi\Command\GetDataCommand;
use PejmanAslani\Asterisk\Agi\Command\GetVariableCommand;
use PejmanAslani\Asterisk\Agi\Command\GotoCommand;
use PejmanAslani\Asterisk\Agi\Command\HangupCommand;
use PejmanAslani\Asterisk\Agi\Command\RecordFileCommand;
use PejmanAslani\Asterisk\Agi\Command\SayNumberCommand;
use PejmanAslani\Asterisk\Agi\Command\SetVariableCommand;
use PejmanAslani\Asterisk\Agi\Command\StreamFileCommand;
use PejmanAslani\Asterisk\Agi\Connection\ConnectionInterface;
use PejmanAslani\Asterisk\Agi\Connection\StandardInputOutputConnection;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * The main AGI class, providing a high-level interface to interact with Asterisk.
 */
class AGI
{
    private ConnectionInterface $connection;
    private LoggerInterface $logger;

    /** @var array<string, string> */
    private array $variables = [];

    /**
     * @param ConnectionInterface|null $connection The connection handler.
     * @param LoggerInterface|null $logger A PSR-3 compliant logger for professional logging.
     */
    public function __construct(
        ?ConnectionInterface $connection = null,
        ?LoggerInterface $logger = null
    ) {
        $this->connection = $connection ?? new StandardInputOutputConnection();
        $this->logger = $logger ?? new NullLogger();

        $this->fetchInitialVariables();

        $this->logger->info('AGI session started.', [
            'channel' => $this->getInitialVariable('agi_channel'),
            'caller_id' => $this->getInitialVariable('agi_callerid'),
        ]);
    }

    /**
     * The core method for running any command.
     */
    public function execute(CommandInterface $command): Response
    {
        $commandString = $command->asString();
        $this->logger->debug('Executing command', ['command' => $commandString]);

        $this->connection->writeLine($commandString);
        $rawResponse = $this->connection->readLine();

        $this->logger->debug('Received response', ['response' => $rawResponse]);
        
        $response = Response::fromString($rawResponse);
        if (!$response->isSuccess()) {
            $this->logger->warning('Command execution resulted in a non-200 response.', [
                'command' => $commandString,
                'response_code' => $response->code,
                'response_result' => $response->result,
                'response_data' => $response->data,
            ]);
        }
        
        return $response;
    }

    private function fetchInitialVariables(): void
    {
        while (($line = $this->connection->readLine()) !== '') {
            if (str_contains($line, ':')) {
                list($key, $value) = explode(':', $line, 2);
                $key = trim($key);
                $value = trim($value);
                $this->variables[$key] = $value;
                $this->logger->debug('Fetched initial variable', [$key => $value]);
            }
        }
    }

    /**
     * Gets an initial AGI variable that was passed at the start of the script.
     */
    public function getInitialVariable(string $key): ?string
    {
        return $this->variables[$key] ?? null;
    }

    /**
     * Sends a VERBOSE message directly to the Asterisk console.
     */
    public function verbose(string $message, int $level = 1): Response
    {
        // This is a direct command and doesn't use the Command class structure for simplicity,
        // but could be refactored into a VerboseCommand class if needed.
        $commandString = 'VERBOSE "' . addslashes($message) . '" ' . $level;
        $this->connection->writeLine($commandString);
        $rawResponse = $this->connection->readLine();
        return Response::fromString($rawResponse);
    }
    
    // --- Call Control Methods ---
    public function answer(): Response { return $this->execute(new AnswerCommand()); }
    public function hangup(): Response { return $this->execute(new HangupCommand()); }
    public function goto(string $context, string $extension, int $priority = 1): Response { return $this->execute(new GotoCommand($context, $extension, $priority)); }
    public function exec(string $application, string ...$options): Response { return $this->execute(new ExecCommand($application, ...$options)); }
    
    // --- Media & Playback Methods ---
    public function streamFile(string $filename, string $escapeDigits = ''): Response { return $this->execute(new StreamFileCommand($filename, $escapeDigits)); }
    public function sayNumber(int $number, string $escapeDigits = ''): Response { return $this->execute(new SayNumberCommand($number, $escapeDigits)); }

    // --- IVR & Data Methods ---
    public function getData(string $filename, int $timeout = 5000, int $maxDigits = 128): Response { return $this->execute(new GetDataCommand($filename, $timeout, $maxDigits)); }
    public function recordFile(string $filename, string $format = 'gsm', string $escapeDigits = '#', int $timeout = -1): Response { return $this->execute(new RecordFileCommand($filename, $format, $escapeDigits, $timeout)); }
    
    // --- Variable & Database Methods ---
    public function getVariable(string $name): Response { return $this->execute(new GetVariableCommand($name)); }
    public function setVariable(string $name, string $value): Response { return $this->execute(new SetVariableCommand($name, $value)); }
    public function databaseGet(string $family, string $key): Response { return $this->execute(new DatabaseGetCommand($family, $key)); }
    public function databasePut(string $family, string $key, string $value): Response { return $this->execute(new DatabasePutCommand($family, $key, $value)); }
}