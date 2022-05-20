<?php
try {
    $mysql = new mysqli('localhost', 'root', 'Gisheh9071', 'oauth_demo');
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    http_response_code(500);
    exit();
}
