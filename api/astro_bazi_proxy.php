<?php
header('Content-Type: application/json; charset=utf-8');

$birthYear = isset($_GET['birth_year']) ? (int)$_GET['birth_year'] : 0;
$date = $_GET['date'] ?? gmdate('Y-m-d');

if ($birthYear < 1900 || $birthYear > 2100) {
    echo json_encode([
        'error' => 'Invalid birth_year. Expected range 1900-2100.'
    ]);
    exit;
}

$zodiacs = [
    'Rat', 'Ox', 'Tiger', 'Rabbit', 'Dragon', 'Snake',
    'Horse', 'Goat', 'Monkey', 'Rooster', 'Dog', 'Pig'
];
$elements = ['Wood', 'Wood', 'Fire', 'Fire', 'Earth', 'Earth', 'Metal', 'Metal', 'Water', 'Water'];

$zodiacIndex = (($birthYear - 4) % 12 + 12) % 12;
$stemIndex = (($birthYear - 4) % 10 + 10) % 10;

$zodiac = $zodiacs[$zodiacIndex];
$element = $elements[$stemIndex];

$pillarHeavenlyStem = ['Jia', 'Yi', 'Bing', 'Ding', 'Wu', 'Ji', 'Geng', 'Xin', 'Ren', 'Gui'][$stemIndex];
$pillarEarthlyBranch = ['Zi', 'Chou', 'Yin', 'Mao', 'Chen', 'Si', 'Wu', 'Wei', 'Shen', 'You', 'Xu', 'Hai'][$zodiacIndex];
$baziSummary = $pillarHeavenlyStem . '-' . $pillarEarthlyBranch . ' (Year Pillar)';

$insights = [
    'Wood' => 'Wood energy cenderung cocok untuk growth, planning, dan habit building.',
    'Fire' => 'Fire energy biasanya kuat di aktivitas sosial dan fase performatif.',
    'Earth' => 'Earth energy stabil saat pola tidur dan nutrisi dijaga konsisten.',
    'Metal' => 'Metal energy kuat pada struktur, fokus, dan rutinitas yang rapi.',
    'Water' => 'Water energy sensitif pada transisi; recovery dan refleksi jadi penting.'
];

echo json_encode([
    'date' => $date,
    'chinese_zodiac' => $zodiac,
    'element' => $element,
    'bazi' => [
        'summary' => $baziSummary,
        'year_pillar' => [
            'heavenly_stem' => $pillarHeavenlyStem,
            'earthly_branch' => $pillarEarthlyBranch
        ]
    ],
    'insight' => $insights[$element] ?? 'Wellness guidance generated from local astrology model.'
]);
