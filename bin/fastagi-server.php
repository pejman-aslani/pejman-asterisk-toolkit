#!/usr/bin/php -q
<?php

// Pejman Asterisk Toolkit - Final Production-Ready FastAGI Server
// This script incorporates all professional features: Dotenv, PSR-3 Logging, and robust error handling.

// ---------------- BOOTSTRAP ----------------
require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Pejman\Asterisk\Agi\AGI;
use Pejman\Asterisk\Agi\Connection\FastAgiConnection;
use Pejman\Asterisk\Agi\Exception\ConnectionException;
use Pejman\Asterisk\Agi\Helpers\Menu;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;


// ---------------- 1. CONFIGURATION LOADING ----------------
// Load environment variables from .env file in the project root.
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Read config from environment, with sensible defaults.
$listenAddress = $_ENV['AGI_LISTEN_ADDRESS'] ?? 'tcp://127.0.0.1:4573';
$logLevelName = $_ENV['LOG_LEVEL'] ?? 'DEBUG';
$logLevel = constant(Logger::class . '::' . strtoupper($logLevelName));


// ---------------- 2. LOGGER SETUP ----------------
$log = new Logger('PejmanAsteriskToolkit');
$handler = new StreamHandler('php://stdout', $logLevel);
$formatter = new LineFormatter(
    "[%datetime%] %channel%.%level_name%: %message% %context%\n",
    "Y-m-d H:i:s", true, true
);
$handler->setFormatter($formatter);
$log->pushHandler($handler);
// For production, you can also add a file handler:
// $log->pushHandler(new StreamHandler('/var/log/your-app.log', Logger::INFO));


// ---------------- SERVER STARTUP ----------------
$log->info("Starting FastAGI server", [
    'listen_address' => $listenAddress,
    'log_level' => $logLevelName
]);

try {
    $serverSocket = stream_socket_server($listenAddress, $errno, $errstr);
} catch (\Exception $e) {
    $log->critical("Failed to create server socket: {$e->getMessage()}");
    exit(1);
}

if (!$serverSocket) {
    $log->critical("Could not start server: $errstr ($errno)");
    exit(1);
}


// ---------------- MAIN SERVER LOOP ----------------
while (true) {
    // This function blocks execution until a new call is received from Asterisk.
    $clientSocket = @stream_socket_accept($serverSocket, -1);

    if ($clientSocket) {
        // 4. Handle each call with robust, multi-level error handling
        try {
            // 3. Instantiate the AGI class, injecting both the connection and the logger.
            $agi = new AGI(
                new FastAgiConnection($clientSocket),
                $log
            );

            // =======================================================
            // YOUR IVR APPLICATION LOGIC STARTS HERE
            // =======================================================

            $agi->answer();
            $agi->verbose("Call handled by Pejman Asterisk Toolkit v1.0"); // Using verbose for Asterisk CLI debug

            $menu = new Menu($agi);
            $menu->prompt('ivr-menu')
                ->option('1', function(AGI $agi) use ($log) {
                    $log->info("User selected option 1: Sales");
                    $agi->streamFile('ivr-sales');
                })
                ->option('2', function(AGI $agi) use ($log) {
                    $log->info("User selected option 2: Support");
                    $agi->streamFile('ivr-support');
                })
                ->onInvalid(fn(AGI $agi) => $agi->streamFile('ivr-invalid'))
                ->execute();

            $agi->hangup();

            // =======================================================
            // YOUR IVR APPLICATION LOGIC ENDS HERE
            // =======================================================

        } catch (ConnectionException $e) {
            // This is an expected event, e.g., the user hung up mid-script.
            $log->info("Connection closed by peer.", ['reason' => $e->getMessage()]);

        } catch (Throwable $e) {
            // This catches any other unexpected error in the application logic.
            // This IS a bug and should be logged as an ERROR.
            $log->error("An uncaught exception occurred.", [
                'exception_class' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}