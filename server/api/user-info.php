<?php
/* @var mysqli $mysql */

const SCOPE = 'user_info';

require_once '../lib/verify-token.php';
require_once '../lib/mysql.php';

$result = $mysql->query("SELECT `username`, `gender`, `date_of_birth`, `country`, `city`, `profile_image` FROM `users` WHERE id = ${attributes['user_id']}");

header('Content-Type: application/json;charset=UTF-8');
echo json_encode($result->fetch_assoc());

$result->free();
$mysql->close();
