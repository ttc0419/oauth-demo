<?php
/**
 * @var mysqli $mysql
 * @var Redis $redis
 * @var string $client_name
 * @var array $requested_permissions
 */

/**
 * Create client redirect url
 *
 * According to https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.2
 * > query component of the redirection URI need to be encoded using
 * > the "application/x-www-form-urlencoded" format
 */
$grant_code = base64_encode(pack('f', microtime(true)) . random_bytes(14));
$redirect_url = "${_GET['redirect_uri']}?code=" . urlencode($grant_code);
if (isset($_GET['state']))
    $redirect_url .= '&state=' . urlencode($_GET['state']);

/**
 * Store grant code in redis with TTL of 5 minutes
 *
 * https://datatracker.ietf.org/doc/html/rfc6749#section-4.1.2 suggested:
 * > A maximum authorization code lifetime of 10 minutes is RECOMMENDED.
 *
 * We also need to store the client_id, user_id and redirect_uri, if your users can
 * select specific scope values they want to permit, you should include the permitted scope as well.
 */
$attributes = [
    'client_id' => $_GET['client_id'],
    'user_id' => $_SESSION['user_id'],
    'scope' => $_GET['scope']
];

if (isset($_GET['redirect_uri']))
    $attributes['redirect_uri'] = $_GET['redirect_uri'];

require_once 'redis.php';
$key = "oauth-demo:grant-code:$grant_code";
$redis->hMSet($key, $attributes);
$redis->expire($key, 300);
$redis->close();

readfile('layouts/top.html');
?>

<div class="card card-body">
    <h3>Callback</h3>
    <p>"<?= $client_name ?>" would like to have the following permissions:</p>
    <ul>
        <?php foreach ($requested_permissions as $permission): ?>
            <li><?= $permission ?></li>
        <?php endforeach; ?>
    </ul>
    <a href="<?= $redirect_url ?>" class="btn btn-primary b-0 text-decoration-none">Accept</a>
</div>

<?php readfile('layouts/bottom.html'); ?>
