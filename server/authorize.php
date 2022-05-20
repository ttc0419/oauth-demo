<?php
/**
 * @var mysqli $mysql
 *
 * To simply the process, we will just assume the redirect_uri and scope are provided
 * in the query parameter.  You should make sure the redirect_uri is under
 * client's domain or ensure the redirect URI is set during client registration.
 */

require_once 'lib/mysql.php';

const VALID_SCOPES = ['user_info', 'contacts'];

/**
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.2.1:
 * > If the request fails due to a missing, invalid, or mismatching redirection URI,
 * > or if the client identifier is missing or invalid,
 * > the authorization server SHOULD inform the resource owner of the error
 * > and MUST NOT automatically redirect the user-agent to the invalid redirection URI.
 *
 * We will use client_name to check if the client_id is valid since we'll use it
 * for access confirmation later anyway
 */
$stmt = $mysql->prepare('SELECT `name` from `clients` WHERE id = ?');
$stmt->bind_param('s', $_GET['client_id']);
$stmt->bind_result($client_name);
$stmt->execute();
$stmt->fetch();
$stmt->close();

if (!isset($_GET['client_id']) || !isset($_GET['redirect_uri']) || $client_name === null) {
    http_response_code(400);
    echo 'client_id or redirect_uri is invalid or missing!';
    exit();
}

function send_error_redirect(string $error): never
{
    http_response_code(302);
    header("Location: ${_GET['redirect_uri']}?error=$error"
        . (isset($_GET['state']) ? "&state=${_GET['state']}" : ''));
    exit();
}

/**
 * Check if the required parameters exits
 * Refer to https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.2.1 for the error types
 */
if (!isset($_GET['response_type']))
    send_error_redirect('invalid_request');

if ($_GET['response_type'] !== 'code')
    send_error_redirect('unsupported_response_type');

/**
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-3.3:
 * > The value of the scope parameter is expressed as a list of space-delimited, case-sensitive strings
 */
$requested_permissions = explode(' ', $_GET['scope']);
if (!isset($_GET['scope']) || array_intersect($requested_permissions, VALID_SCOPES) !== $requested_permissions)
    send_error_redirect('invalid_scope');

session_start();

/**
 * If the user is already logged in and the user is valid, respond with the confirmation page.
 *
 * WARNING:
 * The password should be hashed with salt using password_hash(), to simplify the process, plaintext
 * password is used in this project.  DO NOT use plaintext password in any production environment!
 */
if (isset($_SESSION['user_id'])) {
    $stmt = $mysql->prepare('SELECT COUNT(*) FROM `users` WHERE id = ?');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->bind_result($count);
    $stmt->execute();
    $stmt->fetch();
    $stmt->close();

    if ($count === 1)
        require_once 'lib/confirm.php';
    else
        http_response_code(404);

    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $mysql->prepare('SELECT id FROM `users` WHERE username = ? AND password = ?');
    $stmt->bind_param('ss', $_POST['username'], $_POST['password']);
    $stmt->bind_result($id);
    $stmt->execute();
    $stmt->fetch();
    $stmt->close();

    if ($id === null) {
        http_response_code(404);
    } else {
        $_SESSION['user_id'] = $id;
        require_once 'lib/confirm.php';
    }

    exit();
}

$mysql->close();
session_write_close();
readfile('layouts/top.html');
?>

<div class="card card-body">
    <h3>Login</h3>
    <form action="<?= basename(__FILE__) . '?' . $_SERVER['QUERY_STRING'] ?>" method="post">
        <label for="username">Username</label>
        <input type="text" name="username" id="username" class="form-control mb-2">

        <label for="password">Password</label>
        <input type="password" name="password" id="password" class="form-control mb-2">

        <button type="submit" class="btn btn-primary mt-2">Login</button>
    </form>
</div>

<?php readfile('layouts/bottom.html'); ?>
