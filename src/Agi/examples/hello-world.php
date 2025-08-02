#!/usr/bin/php -q
<?php

// This allows the script to be run directly from the command line or by Asterisk.
// The -q switch prevents PHP from printing HTTP headers.

require __DIR__ . '/../vendor/autoload.php';

use  Pejman\Asterisk\Agi\AGI;

// --- Script Starts Here ---

$agi = new AGI();

$callerId = $agi->getVariable('agi_callerid');
$agi->log("Hello World! The script has started for caller: " . $callerId);

// Let's do something simple and then hang up.
$agi->log("This is the end of our first modern AGI script. Goodbye!");

$agi->hangup();