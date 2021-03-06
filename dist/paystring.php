<?php
require 'vendor/autoload.php';

use Monolog\Logger;
use Monolog\Handler\ErrorLogHandler;

$bitwise = (int) $_GET['bitwise'];
$payStringAddress = $_GET['address-prefix'];

if ($bitwise > 0) {
    $payStringAddress .= '-' . $bitwise;
}

$payStringAddress .= '$paystringvalidator.com';

$acceptHeaderValue = strtolower($_SERVER['HTTP_ACCEPT']);
preg_match(
    '/application\/([\w\-]*)[\+]*json/i',
    $acceptHeaderValue,
    $headerPieces
);

$appLogger = new Logger('app-address');
$appLogger->pushHandler(new ErrorLogHandler());
$appLogger->info('paystring address request; bitwise: ' . $bitwise, [
    'content-type' => $acceptHeaderValue,
]);

// If we cannot parse the content-type requested we should just bail
if (count($headerPieces) !== 2) {
    http_response_code(400);
    exit;
}

$headerSubPieces = explode('-', $headerPieces[1]);

if (count($headerSubPieces) === 1 && $headerSubPieces[0] === 'payid') {
    $network = null;
    $environment = null;
} else if (count($headerSubPieces) === 1 && $headerSubPieces[0] === 'ach') {
    $network = $headerSubPieces[0];
    $environment = 'default';
} else {
    $network = $headerSubPieces[0];
    $environment = $headerSubPieces[1];
}

// This is an ALL request
if ($network === null) {
    $files = glob('./paystring-addresses/*/*.json');
} else {
    // This is a request for specific network/environment combination
    $path = realpath('./paystring-addresses/' . $network . '/' . $environment . '.json');

    if (!$path) {
        http_response_code(404);
        exit;
    }

    $files = [$path];
}

// Now let's pull together all of the needed addresses
$addresses = [];

foreach ($files as $filepath) {
    $addresses[] = json_decode(file_get_contents($filepath));
}

$payload = [
    'payId' => $payStringAddress,
    'addresses' => $addresses,
];

// Now, let's adjust this response for any chosen issues based upon the bitwise operator
$payloadManager = new PayStringValidator\PayloadManager(
    $payload,
    $bitwise
);
echo $payloadManager->deliverPayload();
