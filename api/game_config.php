<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Difficulty presets
$presets = [
    'easy'   => ['game_base_speed' => 1.5,  'game_speed_multiplier' => 0.001, 'game_gravity' => 0.35, 'game_jump_strength' => -12.0, 'game_obstacle_interval' => 120],
    'normal' => ['game_base_speed' => 2.5,  'game_speed_multiplier' => 0.003, 'game_gravity' => 0.45, 'game_jump_strength' => -10.5, 'game_obstacle_interval' => 80],
    'hard'   => ['game_base_speed' => 4.0,  'game_speed_multiplier' => 0.006, 'game_gravity' => 0.60, 'game_jump_strength' => -9.0,  'game_obstacle_interval' => 50],
];

$difficulty = setting($pdo, 'game_difficulty', 'normal');

// If not custom, use preset values
if ($difficulty !== 'custom' && isset($presets[$difficulty])) {
    $p = $presets[$difficulty];
    echo json_encode([
        'difficulty'             => $difficulty,
        'base_speed'             => (float) $p['game_base_speed'],
        'speed_multiplier'       => (float) $p['game_speed_multiplier'],
        'gravity'                => (float) $p['game_gravity'],
        'jump_strength'          => (float) $p['game_jump_strength'],
        'obstacle_interval'      => (int)   $p['game_obstacle_interval'],
    ]);
} else {
    echo json_encode([
        'difficulty'             => $difficulty,
        'base_speed'             => (float) setting($pdo, 'game_base_speed', '2.5'),
        'speed_multiplier'       => (float) setting($pdo, 'game_speed_multiplier', '0.003'),
        'gravity'                => (float) setting($pdo, 'game_gravity', '0.45'),
        'jump_strength'          => (float) setting($pdo, 'game_jump_strength', '-10.5'),
        'obstacle_interval'      => (int)   setting($pdo, 'game_obstacle_interval', '80'),
    ]);
}
