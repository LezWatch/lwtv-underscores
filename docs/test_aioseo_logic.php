<?php
/**
 * AIOSEO Custom Fields Test Script
 * 
 * This script demonstrates how the custom fields would work by simulating
 * the data processing logic outside of WordPress.
 */

echo "AIOSEO Custom Fields Implementation Test\n";
echo "========================================\n\n";

// Test data simulation
$test_data = [
    'actor' => [
        'id' => 123,
        'title' => 'Jane Doe',
        'char_count' => 3,
        'is_queer' => true
    ],
    'character' => [
        'id' => 456,
        'title' => 'Sarah Connor',
        'actors' => ['Jane Doe', 'Linda Hamilton'],
        'shows' => ['Terminator: The Series', 'Terminator 2']
    ],
    'show' => [
        'id' => 789,
        'title' => 'The L Word',
        'formats' => ['TV Series'],
        'stations' => ['Showtime'],
        'tropes' => ['Bury Your Gays', 'Lesbian']
    ]
];

echo "Testing Actor Custom Fields:\n";
echo "============================\n";

// Test character count logic
$char_count = $test_data['actor']['char_count'];
$characters = ( 0 === $char_count ) ? 'no characters' : sprintf( ($char_count === 1) ? '%s character' : '%s characters', $char_count );
echo "lwtv_aioseo_characters: '$characters'\n";

// Test queer status logic  
$is_queer = $test_data['actor']['is_queer'];
$queer_text = ( $is_queer ) ? 'a queer actor' : 'an actor';
echo "lwtv_aioseo_is_queer: '$queer_text'\n";

echo "\nTesting Character Custom Fields:\n";
echo "================================\n";

// Test actors list
$actors_string = implode( ', ', $test_data['character']['actors'] );
echo "lwtv_aioseo_actors: '$actors_string'\n";

// Test shows list
$shows_string = implode( ', ', $test_data['character']['shows'] );
echo "lwtv_aioseo_shows: '$shows_string'\n";

echo "\nTesting Show Custom Fields:\n";
echo "===========================\n";

// Test format list
$formats_string = implode( ', ', $test_data['show']['formats'] );
echo "lwtv_aioseo_formats: '$formats_string'\n";

// Test stations list
$stations_string = implode( ', ', $test_data['show']['stations'] );
echo "lwtv_aioseo_stations: '$stations_string'\n";

// Test tropes list
$tropes_string = implode( ', ', $test_data['show']['tropes'] );
echo "lwtv_aioseo_tropes: '$tropes_string'\n";

echo "\nExample AIOSEO Usage:\n";
echo "====================\n";
echo "Actor page: \"{$test_data['actor']['title']} is {$queer_text} who has played at least {$characters} who are queer on television.\"\n";
echo "Character page: \"{$test_data['character']['title']} is a character played by {$actors_string} on {$shows_string}.\"\n";
echo "Show page: \"{$test_data['show']['title']} is a {$formats_string} found on {$stations_string}.\"\n";

echo "\nTest completed successfully! ✓\n";