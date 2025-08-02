#!/usr/bin/php -q
<?php

require __DIR__ . '/../../../vendor/autoload.php';

use Pejman\Asterisk\Agi\AGI;
use Pejman\Asterisk\Agi\Helpers\AuthenticationHelper;

// Note: This script runs in Standard AGI mode.
// It will not use the FastAGI server or the Monolog logger.

// The constructor defaults to Standard I/O and a NullLogger, so this works.
$agi = new AGI();

try {
    $agi->answer();

    $auth = new AuthenticationHelper($agi);

    // This is our simple "database" for this example
    $validPins = ['1234' => 'Pejman', '5678' => 'Test User'];

    $isAuthenticated = $auth->prompt('auth-enter-pin')
        ->maxAttempts(3)
        ->pinLength(4)
        ->validator(function(string $pin) use ($validPins) {
            return array_key_exists($pin, $validPins);
        })
        ->onSuccess(function(string $pin, AGI $agi) use ($validPins) {
            $username = $validPins[$pin];
            // CORRECTED: Using verbose() to show messages on Asterisk CLI
            $agi->verbose("User {$username} authenticated successfully.");
            $agi->streamFile('auth-success');
            $agi->setVariable('AUTHENTICATED_USER', $username);
        })
        ->onFailure(function(int $attemptsLeft, AGI $agi) {
            // CORRECTED: Using verbose()
            $agi->verbose("PIN entry failed. {$attemptsLeft} attempts remaining.");
            $agi->streamFile('auth-failed');
        })
        ->onMaxAttempts(function(AGI $agi) {
            // CORRECTED: Using verbose()
            $agi->verbose("User reached max attempts. Locking out.");
            $agi->streamFile('auth-locked-out');
        })
        ->execute();

    if ($isAuthenticated) {
        $agi->streamFile('auth-welcome-user');
    }

    $agi->hangup();

} catch (Exception $e) {
    if ($agi) {
        // CORRECTED: Using verbose()
        $agi->verbose("An error occurred: " . $e->getMessage());
        $agi->hangup();
    }
}