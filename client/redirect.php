<?php
/**
 * It is recommended to use the state parameter to prevent CSRF attack
 * Refer to https://datatracker.ietf.org/doc/html/rfc6749#section-10.12 for more information
 *
 * The state parameter need to associate with the current user session.
 * The php session id is used in this demo project.
 */
session_start();
if ($_GET['state'] !== session_id()) {
    http_response_code(400);
    exit();
}
session_write_close();

/* Abort if there are errors */
if (isset($_GET['error'])) {
    http_response_code(400);
    echo $_GET['error'];
    exit();
}

require_once 'config.php';

/**
 * Get access token using the grant code
 *
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-2.3.1:
 * > The client identifier is encoded using the "application/x-www-form-urlencoded" encoding algorithm per Appendix B,
 * > and the encoded value is used as the username; the client password is encoded using the same algorithm
 * > and used as the password.
 */
$curl = curl_init(CONFIG['auth_server'] . '/api/token.php');
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_USERNAME => urlencode(CONFIG['client_id']),
    CURLOPT_PASSWORD => urlencode(CONFIG['client_secret']),
    CURLOPT_POSTFIELDS => http_build_query([
        'grant_type' => 'authorization_code',
        'code' => $_GET['code'],
        'redirect_uri' => CONFIG['redirect_uri']
    ])
]);
$token = json_decode(curl_exec($curl))->access_token;
curl_close($curl);

/* Get user information using the access token */
$curl = curl_init(CONFIG['auth_server'] . '/api/user-info.php?access_token=' . urlencode($token));
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPAUTH => CURLAUTH_BEARER,
    CURLOPT_XOAUTH2_BEARER => $token
]);
$response = curl_exec($curl);

/* Ensure the response is OK before decoding */
$code = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
if ($code !== 200) {
    http_response_code($code);
    exit();
} else {
    $user_info = json_decode($response);
}

curl_close($curl);
readfile('layouts/top.html');
?>

<div class="card card-body">
    <h3>User Information</h3>
    <table class="table-bordered">
        <tbody>
        <?php foreach ($user_info as $k => $v): ?>
            <tr>
                <th><?= $k ?></th>
                <td><?= $v ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php readfile('layouts/bottom.html'); ?>
