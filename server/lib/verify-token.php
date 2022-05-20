<?php
/* @var Redis $redis */

if (!isset($_GET['access_token'])) {
    http_response_code(400);
    exit();
}

require_once 'redis.php';
$attributes = $redis->hGetAll("oauth-demo:access-token:${_GET['access_token']}");

/* Ensure the access token is invalid or the scope does not cover the request endpoint */
if (empty($attributes) || !in_array(SCOPE, explode(' ', $attributes['scope']))) {
    http_response_code(403);
    exit();
}

$redis->close();
