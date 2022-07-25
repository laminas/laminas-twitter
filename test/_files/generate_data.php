<?php

declare(strict_types=1);

use Laminas\Http\Client\Adapter\Curl;
use Laminas\Twitter\Twitter;

require_once __DIR__ . '/../../../../vendor/autoload.php';

$twitter = new Twitter([
    'access_token'        => [
        'token'  => '9453382-o1jag9yrT0ju8zYsIjKfLN2LMauVCqsph7JSGp0E4',
        'secret' => 'mICPQTLPcpcvvTWkc2DjHd3SkWUR6Bq4BD9yJUe4Xs',
    ],
    'oauth_options'       => [
        'consumerKey'    => 'k7lHQfa4D2De6If5orOIfw',
        'consumerSecret' => 'ax6VgjiYA5D75bLudAiC3gQAp63u9O2fV5PnXSd0Dq4',
    ],
    'http_client_options' => [
        'adapter' => Curl::class,
    ],
]);

$response = $twitter->account->verifyCredentials();
if (! $response->isSuccess()) {
    echo "Could not verify credentials!\n";
    var_export($response->getErrors());
    exit(2);
}

$response = $twitter->users->search('Laminas');
if (! $response->isSuccess()) {
    echo "Search failed!\n";
    var_export($response->getErrors());
    exit(2);
}
echo $response->getRawResponse();
