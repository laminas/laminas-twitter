<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../../vendor/autoload.php';
$json = file_get_contents('users.search.raw.json');
$php  = json_decode($json);
$json = json_encode(
    $php,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_SLASHES
);
echo $json;
