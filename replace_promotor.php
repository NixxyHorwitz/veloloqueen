<?php
$orig = file_get_contents(__DIR__ . '/user/promotor.php');
$parts = explode('<style>', $orig, 2);

$newHtml = file_get_contents(__DIR__ . '/scratch_promotor_html.php');
// Only remove the specific header block I added:
$newHtml = str_replace("<?php\n// We will replace everything from <style> onwards in promotor.php with this file's contents.\n?>\n", '', $newHtml);

file_put_contents(__DIR__ . '/user/promotor.php', $parts[0] . $newHtml);
echo "Replaced successfully with PHP intact.";
