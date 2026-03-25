<?php
// Local API config for localhost (XAMPP).
// Fill values below. Keep empty string if not used.
return [
    // lunar-mcp-server HTTP bridge endpoint
    // Example: http://127.0.0.1:3001/mcp
    'LUNAR_MCP_URL' => '',
    'LUNAR_MCP_API_KEY' => '',
    'LUNAR_MCP_API_HOST' => '',

    // Moon Phase API template
    // Use {date} (YYYY-MM-DD) or {timestamp} placeholder.
    // Example: https://api.example.com/moon?date={date}
    'MOON_PHASE_API_URL' => 'https://api.freeastroapi.com/api/v1/moon/phase?date={date}T12:00:00Z',
    'MOON_PHASE_API_KEY' => '4e7e00201ea72328cca3ca6e4ebf517c6540dd7871d44c15c35a77bde1c58e28',
    'MOON_PHASE_API_HOST' => '',

    // Astrology + BaZi / Chinese Calendar API template
    // Use {birth_year} and {date} placeholders.
    // Example: https://api.example.com/bazi?birth_year={birth_year}&date={date}
    'ASTRO_BAZI_API_URL' => 'https://astro-api-1qnc.onrender.com/api/v1/natal/calculate',
    'ASTRO_BAZI_API_METHOD' => 'POST',
    'ASTRO_BAZI_API_KEY' => '',
    'ASTRO_BAZI_API_HOST' => '',
    'ASTRO_BAZI_CITY' => 'Jakarta, Indonesia',
    'ASTRO_BAZI_LAT' => '-6.2088',
    'ASTRO_BAZI_LNG' => '106.8456',
    'ASTRO_BAZI_TZ' => 'AUTO',
    'ASTRO_BAZI_BIRTH_MONTH' => '6',
    'ASTRO_BAZI_BIRTH_DAY' => '15',
    'ASTRO_BAZI_BIRTH_HOUR' => '12',
    'ASTRO_BAZI_BIRTH_MINUTE' => '0',

    // OpenRouteService API key (for Places/Geocoding autocomplete)
    'ORS_API_KEY' => ''
];
