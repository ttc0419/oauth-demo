<?php

try {
    $redis = new Redis();
    $redis->connect('/tmp/redis.sock');
} catch (Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    http_response_code(500);
    exit();
}
