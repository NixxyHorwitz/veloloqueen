<?php
// Read the original file
$content = file_get_contents('c:/laragon/www/velostar/user/missions.php');

// Find where <style> starts  
$stylePos = strpos($content, '<style>');
echo "Style tag at byte: $stylePos\n";

// Extract only the PHP logic part (before <style>)
$phpPart = substr($content, 0, $stylePos);

echo "PHP part length: " . strlen($phpPart) . "\n";
echo "Last 100 chars of PHP part:\n";
echo substr($phpPart, -100) . "\n";
