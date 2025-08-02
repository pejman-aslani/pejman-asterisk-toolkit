#!/usr/bin/php
<?php

// Pejman Asterisk Toolkit - Advanced, Interactive AMI Server

require __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Pejman\Asterisk\Ami\AmiClient;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use React\EventLoop\Loop;

// --- Load Configuration & Setup Logger/Loop (same as before) ---
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$amiConfig = [
    'host' => $_ENV['AMI_HOST'] ?? '127.0.0.1',
    'port' => (int)($_ENV['AMI_PORT'] ?? 5038),
    'username' => $_ENV['AMI_USER'] ?? '',
    'secret' => $_ENV['AMI_SECRET'] ?? '',
];
$logLevel = constant(Logger::class . '::' . ($_ENV['LOG_LEVEL'] ?? 'DEBUG'));
$log = new Logger('PejmanAmiClient');
$log->pushHandler(new StreamHandler('php://stdout', $logLevel));
$loop = Loop::get();

// --- Application ---
$amiClient = new AmiClient($loop, $amiConfig, $log);

// --- Define Event Listeners ---

$amiClient->on('connect', function(AmiClient $client) use ($log) {
    $log->info("Event Listener: Connection established. Ready to listen and act.");

    // Example of a timed action: Originate a call after 10 seconds
    /*
    Loop::addTimer(10, function() use ($client, $log) {
        $log->info("Originating a test call...");
        $client->originate('SIP/101', 'from-internal', 's', 1, ['CallerID' => 'AMI Test'])
            ->then(
                fn($res) => $log->info("Originate action sent."),
                fn($err) => $log->error("Originate action failed.", $err)
            );
    });
    */
});

$amiClient->on('newchannel', function(array $event, AmiClient $client) use ($log) {
    $log->info("EVENT: New call detected!", [
        'channel' => $event['Channel'],
        'callerId' => $event['CallerIDNum'],
    ]);

    // --- ACTIVE CONTROLLER LOGIC ---
    // In response to the new call, let's get all active channels.
    $log->info("ACTION: A new call started, fetching all current channels...");
    $client->coreShowChannels()->then(
        function(array $channels) use ($log) {
            $log->notice("RESPONSE: System now has " . count($channels) . " active channels.", [
                'channels_list' => array_column($channels, 'Channel') // Show a clean list of channel names
            ]);
        },
        function(array $error) use ($log) {
            $log->error("ACTION FAILED: Could not get channel list.", ['error' => $error]);
        }
    );
});

$amiClient->on('hangup', function(array $event) use ($log) {
    $log->info("EVENT: Call ended.", [
        'channel' => $event['Channel'],
        'cause' => $event['Cause-txt'],
    ]);
});

$amiClient->on('peerstatus', function(array $event) use ($log) {
    $log->notice("EVENT: Peer status changed.", [
        'peer' => $event['Peer'],
        'status' => $event['PeerStatus'],
    ]);
});


// --- Start the client and the loop ---
$amiClient->connect();
$log->info('AMI client is running. Waiting for events...');
$loop->run();