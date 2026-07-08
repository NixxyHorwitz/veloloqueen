<?php
$orig = file_get_contents(__DIR__ . '/user/promotor.php');
$parts = explode('<style>', $orig, 2);

$newHtml = file_get_contents(__DIR__ . '/scratch_promotor_html.php');
$newHtml = preg_replace('/<\?php.*?\?>\n/s', '', $newHtml);

file_put_contents(__DIR__ . '/user/promotor.php', $parts[0] . $newHtml);
echo "Replaced successfully.";
