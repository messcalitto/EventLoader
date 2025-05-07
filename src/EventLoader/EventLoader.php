<?php

declare(strict_types=1);

namespace App\EventLoader;

use App\Config\EnvLoader;
use App\EventLoader\Interface\EventSourceInterface;
use App\EventLoader\Interface\EventStorageInterface;
use App\EventLoader\Interface\LockManagerInterface;
use App\EventLoader\Interface\RequestTimeManagerInterface;
use App\EventLoader\Mock\LoggerInterface;

class EventLoader
{
    /** @var array<EventSourceInterface> */
    private array $eventSources;
    
    public function __construct(
        private readonly EventStorageInterface $storage,
        private readonly LockManagerInterface $lockManager,
        private readonly LoggerInterface $logger,
        private readonly RequestTimeManagerInterface $requestTimeManager,
        array $eventSources = [],
    ) {
        $this->eventSources = $eventSources;
    }
    
    /**
     * Load events from all sources in a cycle.
     * 
     * @param int $cycles Number of loading cycles to perform (0 for infinite)
     * @return int Number of events loaded
     */
    public function loadEvents(int $cycles = 1): int
    {
        $totalEventsLoaded = 0;
        $cycleCount = 0;
        
        do {
            $cycleEventsLoaded = 0;
            
            foreach ($this->eventSources as $source) {
                $sourceName = $source->getName();
                
                // Respect the minimum interval between requests to the same source
                $this->requestTimeManager->waitForMinimumInterval($sourceName, EnvLoader::get('MINIMUM_REQUEST_INTERVAL', 200));
                
                // Try to acquire a lock for this source
                if (!$this->lockManager->acquireLock($sourceName)) {
                    $this->logger->info("Source {$sourceName} is currently being processed by another loader");
                    continue;
                }
                
                try {
                    // Get the last event ID for this source
                    $lastEventId = $this->storage->getLastEventId($sourceName);
                    
                    // Fetch new events
                    $events = $source->fetchEvents($lastEventId);
                    
                    // Record the request time
                    $this->requestTimeManager->setLastRequestTime($sourceName, time());
                    
                    if (empty($events)) {
                        $this->logger->debug("No new events from source {$sourceName}");
                        continue;
                    }
                    
                    // Ensure we don't exceed the maximum events per request
                    $maxEventsPerRequest = EnvLoader::get('MAX_EVENTS_PER_REQUEST', 1000);
                    if (count($events) > $maxEventsPerRequest) {
                        $events = array_slice($events, 0, $maxEventsPerRequest);
                        $this->logger->warning("Source {$sourceName} returned more than the maximum allowed events ({$maxEventsPerRequest})");
                    }
                    
                    // Store the events
                    $this->storage->storeEvents($events);
                    
                    $eventsCount = count($events);
                    $cycleEventsLoaded += $eventsCount;
                    $this->logger->info("Loaded {$eventsCount} events from source {$sourceName}");
                    
                } catch (\Exception $e) {
                    $this->logger->error("Error loading events from source {$sourceName}: " . $e->getMessage());
                } finally {
                    // Always release the lock
                    $this->lockManager->releaseLock($sourceName);
                }
            }
            
            $totalEventsLoaded += $cycleEventsLoaded;
            $cycleCount++;
            
            // If no events were loaded in this cycle, add a small delay to avoid CPU spinning
            if ($cycleEventsLoaded === 0) {
                usleep(100000); // 100ms
            }
            
        } while (($cycles === 0 || $cycleCount < $cycles) && $this->shouldContinue());
        
        return $totalEventsLoaded;
    }
    
    /**
     * Determine if the loading process should continue.
     * This can be extended to handle graceful shutdown.
     */
    private function shouldContinue(): bool
    {
        // In a real implementation, this could check for shutdown signals
        return true;
    }
    
    /**
     * Add an event source to the loader.
     */
    public function addEventSource(EventSourceInterface $source): self
    {
        $this->eventSources[] = $source;
        return $this;
    }
}
