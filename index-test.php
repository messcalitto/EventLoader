<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use App\EventLoader\EventLoader;
use App\EventLoader\Mock\ConsoleLogger;
use App\EventLoader\Mock\InMemoryEventStorage;
use App\EventLoader\Mock\InMemoryLockManager;
use App\EventLoader\Mock\InMemoryRequestTimeManager;
use App\EventLoader\Source\UrlEventSource;
use App\EventLoader\Source\MockEventSource;

// Create the logger with debug mode enabled
$logger = new ConsoleLogger(true);

// Create in-memory components for testing
$storage = new InMemoryEventStorage();
$lockManager = new InMemoryLockManager($logger);
$requestTimeManager = new InMemoryRequestTimeManager($logger);


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
$eventLoader = new EventLoader($storage, $lockManager, $logger, $requestTimeManager, $sources);

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

// Group events by source
$eventsBySource = [];
foreach ($allEvents as $event) {
    $sourceName = $event['source'];
    if (!isset($eventsBySource[$sourceName])) {
        $eventsBySource[$sourceName] = [];
    }
    $eventsBySource[$sourceName][] = $event;
}

// Display count by source
foreach ($eventsBySource as $sourceName => $events) {
    echo "Events from {$sourceName}: " . count($events) . PHP_EOL;
}

// Display the most recent events from each source
echo "\nMost recent events from each source:" . PHP_EOL;
foreach ($eventsBySource as $sourceName => $events) {
    $lastEvent = end($events);
    echo "{$sourceName}: Event ID {$lastEvent['id']}" . PHP_EOL;
}

// Display lock status
echo "\nLock status:" . PHP_EOL;
foreach ($sources as $source) {
    $sourceName = $source->getName();
    $isLocked = $lockManager->isLocked($sourceName);
    $status = $isLocked ? "Locked" : "Unlocked";
    echo "{$sourceName}: {$status}" . PHP_EOL;
}
