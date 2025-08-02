<?php

declare(strict_types=1);

namespace Pejman\Asterisk\Ami;

use Evenement\EventEmitter;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use React\EventLoop\LoopInterface;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Socket\Connector;
use React\Socket\ConnectionInterface as ReactConnection;

/**
 * A comprehensive, resilient, event-driven, and Promise-based client for AMI.
 */
class AmiClient extends EventEmitter
{
    private ?ReactConnection $stream = null;
    private string $buffer = '';
    private array $pendingActions = [];
    private bool $isConnected = false;

    public function __construct(
        private readonly LoopInterface $loop,
        private readonly array $options,
        private ?LoggerInterface $logger = null
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function connect(): void
    {
        if ($this->isConnected || $this->stream) {
            $this->logger->warning('Connect called while already connected or connecting.');
            return;
        }

        $connector = new Connector($this->loop);
        $host = $this->options['host'] ?? '127.0.0.1';
        $port = $this->options['port'] ?? 5038;

        $this->logger->info('Attempting to connect to AMI.', ['host' => $host, 'port' => $port]);

        $connector->connect("{$host}:{$port}")->then(
            function (ReactConnection $stream) {
                $this->stream = $stream;
                $this->logger->info('Successfully connected to AMI socket.');
                $this->emit('connect', [$this]);

                $stream->on('data', [$this, 'handleData']);
                $stream->on('close', [$this, 'handleClose']);
                $this->login();
            },
            fn (\Exception $e) => $this->handleClose($e)
        );
    }

    public function sendAction(array $action): PromiseInterface
    {
        if (!$this->isConnected) {
            return \React\Promise\reject(new \RuntimeException('Not authenticated to AMI.'));
        }

        $deferred = new Deferred();
        $action['ActionID'] = uniqid('ami_action_', true);
        $this->pendingActions[$action['ActionID']] = ['deferred' => $deferred, 'response_events' => []];

        $actionString = '';
        foreach ($action as $key => $value) {
            $actionString .= "{$key}: {$value}\r\n";
        }
        $actionString .= "\r\n";

        $this->stream->write($actionString);
        $this->logger->debug('Action sent.', ['action' => $action['Action']]);

        return $deferred->promise();
    }

    // =======================================================
    // COMPREHENSIVE HELPER METHODS LIBRARY
    // =======================================================

    // --- Call Control Helpers ---
    public function originate(string $channel, string $context, string $extension, int $priority, array $options = []): PromiseInterface {
        $action = array_merge(['Action' => 'Originate', 'Channel' => $channel, 'Context' => $context, 'Exten' => $extension, 'Priority' => $priority, 'Async' => 'true'], $options);
        return $this->sendAction($action);
    }
    public function hangup(string $channel, int $cause = 16): PromiseInterface {
        return $this->sendAction(['Action' => 'Hangup', 'Channel' => $channel, 'Cause' => $cause]);
    }
    public function redirect(string $channel, string $context, string $extension, int $priority): PromiseInterface {
        return $this->sendAction(['Action' => 'Redirect', 'Channel' => $channel, 'Context' => $context, 'Exten' => $extension, 'Priority' => $priority]);
    }

    // --- Channel & System Status Helpers ---
    public function coreShowChannels(): PromiseInterface { return $this->sendAction(['Action' => 'CoreShowChannels']); }
    public function getVar(string $channel, string $variable): PromiseInterface { return $this->sendAction(['Action' => 'Getvar', 'Channel' => $channel, 'Variable' => $variable]); }
    public function setVar(string $channel, string $variable, string $value): PromiseInterface { return $this->sendAction(['Action' => 'Setvar', 'Channel' => $channel, 'Variable' => $variable, 'Value' => $value]); }
    public function ping(): PromiseInterface { return $this->sendAction(['Action' => 'Ping']); }
    public function coreStatus(): PromiseInterface { return $this->sendAction(['Action' => 'CoreStatus']); }

    // --- Peer Status Helpers ---
    public function pjsipShowEndpoints(): PromiseInterface { return $this->sendAction(['Action' => 'PJSIPShowEndpoints']); }
    public function sipPeers(): PromiseInterface { return $this->sendAction(['Action' => 'SIPpeers']); }

    // --- Queue Management Helpers ---
    public function queueStatus(string $queueName = ''): PromiseInterface { return $this->sendAction(['Action' => 'QueueStatus', 'Queue' => $queueName]); }
    public function queueAdd(string $queue, string $interface, int $penalty = 0, bool $paused = false, string $memberName = ''): PromiseInterface {
        return $this->sendAction(['Action' => 'QueueAdd', 'Queue' => $queue, 'Interface' => $interface, 'Penalty' => $penalty, 'Paused' => $paused ? 'true' : 'false', 'MemberName' => $memberName]);
    }
    public function queueRemove(string $queue, string $interface): PromiseInterface {
        return $this->sendAction(['Action' => 'QueueRemove', 'Queue' => $queue, 'Interface' => $interface]);
    }
    public function queuePause(string $queue, string $interface, bool $paused, string $reason = ''): PromiseInterface {
        return $this->sendAction(['Action' => 'QueuePause', 'Queue' => $queue, 'Interface' => $interface, 'Paused' => $paused ? 'true' : 'false', 'Reason' => $reason]);
    }

    // --- CORE INTERNAL METHODS ---

    private function login(): void
    {
        $this->sendAction([
            'Action' => 'Login',
            'Username' => $this->options['username'] ?? '',
            'Secret' => $this->options['secret'] ?? '',
            'Events' => 'on',
        ])->then(
            function($response) {
                $this->isConnected = true;
                $this->logger->info('AMI Login successful.');
                $this->emit('login_success', [$response]);
            },
            function($error) {
                $this->isConnected = false;
                $this->logger->error('AMI Login failed.', $error);
                $this->emit('login_failed', [$error]);
                if ($this->stream) $this->stream->close();
            }
        );
    }

    private function parseAndDispatch(string $messageStr): void {
        $message = [];
        foreach (explode("\r\n", $messageStr) as $line) {
            if (strpos($line, ': ') !== false) { list($key, $value) = explode(': ', $line, 2); $message[trim($key)] = trim($value); }
        }
        if (empty($message)) return;
        $actionId = $message['ActionID'] ?? null;
        if ($actionId && isset($this->pendingActions[$actionId])) {
            $pending = &$this->pendingActions[$actionId];
            if (isset($message['Response'])) {
                if (strtolower($message['Response']) === 'error') { $pending['deferred']->reject($message); }
                else {
                    if (isset($message['Message']) && !isset($message['Event'])) { $pending['response_events'][] = $message; }
                    $pending['deferred']->resolve($pending['response_events']);
                }
                unset($this->pendingActions[$actionId]);
            } elseif (isset($message['Event'])) {
                $pending['response_events'][] = $message;
                if (isset($message['EventList']) && $message['EventList'] == 'Complete') {
                    $pending['deferred']->resolve($pending['response_events']);
                    unset($this->pendingActions[$actionId]);
                }
            }
        } elseif (isset($message['Event'])) {
            $eventName = strtolower($message['Event']);
            $this->emit($eventName, [$message, $this]);
            $this->emit('*', [$message, $this]);
        }
    }

    private function handleData(string $chunk): void {
        $this->buffer .= $chunk;
        while (($pos = strpos($this->buffer, "\r\n\r\n")) !== false) {
            $messageStr = substr($this->buffer, 0, $pos);
            $this->buffer = substr($this->buffer, $pos + 4);
            $this->parseAndDispatch($messageStr);
        }
    }

    private function handleClose(\Throwable $error = null): void {
        if ($error) {
            $this->logger->error('AMI connection failed or unexpectedly closed.', ['error' => $error->getMessage()]);
        } else {
            $this->logger->warning('AMI connection closed.');
        }
        $this->isConnected = false;
        $this->stream = null;
        $this->emit('close', [$this]);
        foreach ($this->pendingActions as $pending) {
            $pending['deferred']->reject(new \RuntimeException('Connection closed.'));
        }
        $this->pendingActions = [];

        // --- AUTOMATIC RECONNECTION LOGIC ---
        if ($this->options['auto_reconnect'] ?? true) {
            $delay = $this->options['reconnect_delay'] ?? 5;
            $this->logger->info("Attempting to reconnect in {$delay} seconds...");
            $this->loop->addTimer($delay, [$this, 'connect']);
        }
    }
}