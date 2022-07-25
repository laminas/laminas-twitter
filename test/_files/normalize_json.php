<?php

declare(strict_types=1);

use Laminas\Json\Json;

require_once __DIR__ . '/../../../../vendor/autoload.php';
$json = file_get_contents('users.search.raw.json');
$php  = Json::decode($json);
$json = Json::encode(
    $php,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES
);
$json = Json::prettyPrint($json, ['indent' => '  ']);
echo $json;
