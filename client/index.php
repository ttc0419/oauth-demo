<?php
require_once 'config.php';

session_start();
readfile('layouts/top.html');
?>

<div class="card card-body">
    <h3>Client Login</h3>
    <p>Click the following button to login with OAuth 2.0:</p>
    <a href="<?= CONFIG['auth_server'] ?>/authorize.php?client_id=<?= urlencode(CONFIG['client_id']) ?>&redirect_uri=<?= urlencode(CONFIG['redirect_uri']) ?>&response_type=code&scope=user_info%20contacts&state=<?= urlencode(session_id()) ?>" class="btn btn-primary">
        Login with OAuth 2.0
    </a>
</div>

<?php readfile('layouts/bottom.html'); ?>
