<?php
/**
 * @var mysqli $mysql
 * @var Redis $redis
 */

const TOKEN_LIFETIME = 86400;

/**
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-3.2:
 * > The client MUST use the HTTP "POST" method when making access token requests.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit();
}

function send_auth_error(string $error): never
{
    http_response_code(400);
    echo json_encode(['error' => $error]);
    exit();
}

/**
 * Check if required parameters exist and valid
 *
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.3:
 * > grant_type: REQUIRED and must be "authorization_code"
 * > code: REQUIRED
 * > redirect_uri: REQUIRED if the "redirect_uri" parameter was included in the authorization request
 * > client_id: REQUIRED
 *
 * For the client authentication errors,
 * please refer to https://datatracker.ietf.org/doc/html/rfc6749#section-5.2
 *
 * Since we authenticate clients using HTTP Basic scheme,
 * the client_id and client_secret are sent by Authorization header.
 */
if (!isset($_POST['grant_type']) || !isset($_POST['code']))
    send_auth_error('invalid_request');

if ($_POST['grant_type'] !== 'authorization_code')
    send_auth_error('unsupported_grant_type');

/**
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-2.3.1:
 * > The authorization server MUST support the HTTP Basic authentication scheme
 * > for authenticating clients that were issued a client password
 *
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-2.3.1:
 * > The client identifier is encoded using the "application/x-www-form-urlencoded" encoding algorithm per Appendix B,
 * > and the encoded value is used as the username; the client password is encoded using the same algorithm
 * > and used as the password.
 */
$client_id = urldecode($_SERVER['PHP_AUTH_USER']);
$client_secret = urldecode($_SERVER['PHP_AUTH_PW']);

require_once '../lib/mysql.php';

$stmt = $mysql->prepare('SELECT COUNT(*) from `clients` WHERE id = ? AND secret = ?');
$stmt->bind_param('ss', $client_id, $client_secret);
$stmt->bind_result($client_count);
$stmt->execute();
$stmt->fetch();
$stmt->close();
$mysql->close();

/**
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-5.2:
 * > If the client attempted to authenticate via the "Authorization" request header field,
 * > the authorization server MUST respond with an HTTP 401 (Unauthorized) status code and
 * > include the "WWW-Authenticate" response header field matching the authentication scheme used by the client.
 */
if ($client_count !== 1) {
    http_response_code(401);
    header('WWW-Authenticate: Basic');
    exit();
}

require_once '../lib/redis.php';

$grant_code_key = "oauth-demo:grant-code:${_POST['code']}";
$code_attributes = $redis->hGetAll($grant_code_key);

if ($code_attributes['client_id'] !== $client_id)
    send_auth_error('invalid_client');

if (empty($code_attributes) || (isset($code_attributes['redirect_uri'])
        && isset($_POST['redirect_uri']) && $code_attributes['redirect_uri'] !== $_POST['redirect_uri']))
    send_auth_error('invalid_grant');

/* All the checks are passed, issue access token */
$token = base64_encode(pack('f', microtime(true)) . random_bytes(14));

/* Store token in redis */
$key = "oauth-demo:access-token:$token";
$redis->hMSet($key, ['user_id' => $code_attributes['user_id'], 'scope' => $code_attributes['scope']]);
$redis->expire($key, TOKEN_LIFETIME);

/* Remove grant code from redis */
$redis->del($grant_code_key);
$redis->close();

/* Send token response */
header('Cache-Control: no-store');
header('Content-Type: application/json;charset=UTF-8');

echo json_encode([
    'access_token' => $token,
    'token_type' => 'bearer',
    'expires_in' => TOKEN_LIFETIME
]);
