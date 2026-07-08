<?php
require 'c:\laragon\www\velostar\bootstrap.php';
$stmt = $pdo->query('SHOW COLUMNS FROM users');
while ($row = $stmt->fetch()) echo $row['Field'] . "\n";
