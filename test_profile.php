<?php
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/profile';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'velostar.test';

require 'c:\laragon\www\velostar\bootstrap.php';
$stmt = $pdo->query("SELECT * FROM users LIMIT 1");
$user = $stmt->fetch();
$_SESSION['user_id'] = $user['id'];

ob_start();
require 'c:\laragon\www\velostar\user\profile.php';
$out = ob_get_clean();
echo "Output length: " . strlen($out) . "\n";
if (strlen($out) < 2000) {
    echo "Short output:\n";
    echo $out;
}
