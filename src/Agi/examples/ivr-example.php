#!/usr/bin/php -q
<?php

require __DIR__ . '/../vendor/autoload.php';

use Pejman\Asterisk\Agi\AGI;
use Pejman\Asterisk\Agi\Helpers\Menu; // Import our new helper

// Sound files needed are the same as before.

$agi = new AGI();

try {
    $agi->answer();
    $agi->streamFile('ivr-welcome');

    $menu = new Menu($agi);

    $menu->prompt('ivr-menu')
        ->withConfig(5000, 1) // Optional: configure timeout and digits
        ->option('1', function (AGI $agi) {
            $agi->log("User chose 'Sales'");
            $agi->streamFile('ivr-sales');
        })
        ->option('2', function (AGI $agi) {
            $agi->log("User chose 'Support'");
            $agi->streamFile('ivr-support');
        })
        ->onInvalid(function (AGI $agi) {
            $agi->log("User entered an invalid option.");
            $agi->streamFile('ivr-invalid');
        })
        ->onTimeout(function(AGI $agi){
            $agi->log("User input timed out.");
            // If onTimeout is not defined, onInvalid would be called automatically.
        })
        ->execute();

    $agi->streamFile('ivr-goodbye');
    $agi->hangup();

} catch (Exception $e) {
    $agi->log("An error occurred in the IVR script: " . $e->getMessage());
    if ($agi) {
        $agi->hangup();
    }
}