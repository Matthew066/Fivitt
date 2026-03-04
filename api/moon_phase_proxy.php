<?php
header('Content-Type: application/json; charset=utf-8');

$date = $_GET['date'] ?? '';
$timestamp = $_GET['timestamp'] ?? '';

if ($date === '' && $timestamp !== '' && ctype_digit((string)$timestamp)) {
    $date = gmdate('Y-m-d', (int)$timestamp);
}

if ($date === '') {
    $date = gmdate('Y-m-d');
}

$target = strtotime($date . ' 12:00:00 UTC');
$knownNewMoon = strtotime('2000-01-06 18:14:00 UTC');
$synodicMonth = 29.53058867;

if ($target === false || $knownNewMoon === false) {
    echo json_encode([
        'phase_name' => 'Unknown',
        'illumination' => 0,
        'age' => 0
    ]);
    exit;
}

$daysSince = ($target - $knownNewMoon) / 86400;
$age = fmod($daysSince, $synodicMonth);
if ($age < 0) {
    $age += $synodicMonth;
}

if ($age < 1.84566) $phase = 'New Moon';
elseif ($age < 5.53699) $phase = 'Waxing Crescent';
elseif ($age < 9.22831) $phase = 'First Quarter';
elseif ($age < 12.91963) $phase = 'Waxing Gibbous';
elseif ($age < 16.61096) $phase = 'Full Moon';
elseif ($age < 20.30228) $phase = 'Waning Gibbous';
elseif ($age < 23.99361) $phase = 'Last Quarter';
elseif ($age < 27.68493) $phase = 'Waning Crescent';
else $phase = 'New Moon';

$illumination = (1 - cos(2 * M_PI * $age / $synodicMonth)) / 2;

echo json_encode([
    'phase_name' => $phase,
    'illumination' => round($illumination * 100, 2),
    'age' => round($age, 2)
]);
