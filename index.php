<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Config\EnvLoader;
use App\EventLoader\EventLoader;
use App\EventLoader\Infrastructure\DatabaseEventStorage;
use App\EventLoader\Infrastructure\DatabaseLockManager;
use App\EventLoader\Infrastructure\RequestTimeManager;
use App\EventLoader\Mock\ConsoleLogger;
use App\EventLoader\Source\UrlEventSource;

// Load environment variables
EnvLoader::load(__DIR__ . '/.env');

// Create the logger with debug mode from environment
$debug = EnvLoader::get('DEBUG', false);
$logger = new ConsoleLogger($debug);

// Create a PDO instance for database connection
// All event load instances will share same database connection
// to make sure multiple instances be synchronized using same lock/unlock mechanism
// and same request time manager
// This is important for the event loader to work correctly

try {
    // Get database configuration from environment
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        EnvLoader::get('DB_HOST', 'localhost'),
        EnvLoader::get('DB_NAME', 'event_loader'),
        EnvLoader::get('DB_CHARSET', 'utf8mb4')
    );
    $username = EnvLoader::get('DB_USER', 'root');
    $password = EnvLoader::get('DB_PASS', '');
    
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    
} catch (PDOException $e) {
    $logger->error("Database connection error: " . $e->getMessage());
    exit(1);
}

$storage = new DatabaseEventStorage($pdo, $logger);
$lockManager = new DatabaseLockManager($pdo, $logger);
$timeManager = new RequestTimeManager($pdo, $logger);


// Create URL-based event sources
$sources = [
    // GitHub Events API
    new UrlEventSource(
        'github-events',
        'https://api.github.com/events',
        $logger
    ),
    
    // JSONPlaceholder API (fake online REST API for testing)
    new UrlEventSource(
        'jsonplaceholder-posts',
        'https://jsonplaceholder.typicode.com/posts',
        $logger
    ),
    
];

// Create the event loader
$eventLoader = new EventLoader($storage, $lockManager, $logger, $timeManager, $sources);

// Run the event loader for 3 cycles
echo "Starting event loading process for 3 cycles..." . PHP_EOL;
$totalEvents = $eventLoader->loadEvents(3);
echo "Event loading completed. Total events loaded: $totalEvents" . PHP_EOL;

// Add a new URL-based source dynamically
$newSource = new UrlEventSource(
    'stackoverflow-api',
    'https://api.stackexchange.com/2.3/questions?order=desc&sort=activity&site=stackoverflow',
    $logger
);

// Add the new source and run for 2 more cycles
$eventLoader->addEventSource($newSource);
echo "Added a new event source. Running for 2 more cycles..." . PHP_EOL;
$additionalEvents = $eventLoader->loadEvents(2);
echo "Additional event loading completed. Additional events loaded: $additionalEvents" . PHP_EOL;
echo "Total events loaded: " . ($totalEvents + $additionalEvents) . PHP_EOL;

// Display some stats about stored events
$allEvents = $storage->getAllEvents();
echo "Total events in storage: " . count($allEvents) . PHP_EOL;
