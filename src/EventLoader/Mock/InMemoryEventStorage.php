<?php

declare(strict_types=1);

namespace App\EventLoader\Mock;

use App\EventLoader\Interface\EventStorageInterface;

/**
 * In-memory implementation of EventStorageInterface for testing purposes.
 */
class InMemoryEventStorage implements EventStorageInterface
{
    /** @var array<string, array<int, array>> Events stored by source name */
    private array $eventsBySource = [];
    
    /** @var array<string, int> Last event ID for each source */
    private array $lastEventIds = [];
    
    /**
     * {@inheritdoc}
     */
    public function storeEvents(array $events): void
    {
        foreach ($events as $event) {
            if (!isset($event['id']) || !isset($event['source'])) {
                continue; // Skip events without required fields
            }
            
            $sourceName = $event['source'];
            $eventId = (int)$event['id'];
            
            // Initialize source array if it doesn't exist
            if (!isset($this->eventsBySource[$sourceName])) {
                $this->eventsBySource[$sourceName] = [];
            }
            
            // Store the event
            $this->eventsBySource[$sourceName][$eventId] = $event;
            
            // Update the last event ID for this source if needed
            if (!isset($this->lastEventIds[$sourceName]) || $eventId > $this->lastEventIds[$sourceName]) {
                $this->lastEventIds[$sourceName] = $eventId;
            }
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function getLastEventId(string $sourceName): ?int
    {
        return $this->lastEventIds[$sourceName] ?? null;
    }
    
    /**
     * Get all events from all sources.
     * 
     * @return array All stored events
     */
    public function getAllEvents(): array
    {
        $allEvents = [];
        
        foreach ($this->eventsBySource as $sourceEvents) {
            foreach ($sourceEvents as $event) {
                $allEvents[] = $event;
            }
        }
        
        return $allEvents;
    }
    
    /**
     * Get all events from a specific source.
     * 
     * @param string $sourceName The source name
     * @return array Events from the specified source
     */
    public function getEventsFromSource(string $sourceName): array
    {
        return array_values($this->eventsBySource[$sourceName] ?? []);
    }
    
    /**
     * Get the total number of events stored.
     * 
     * @return int Total number of events
     */
    public function getEventCount(): int
    {
        $count = 0;
        
        foreach ($this->eventsBySource as $sourceEvents) {
            $count += count($sourceEvents);
        }
        
        return $count;
    }
    
    /**
     * Get the number of events stored for a specific source.
     * 
     * @param string $sourceName The source name
     * @return int Number of events for the source
     */
    public function getSourceEventCount(string $sourceName): int
    {
        return count($this->eventsBySource[$sourceName] ?? []);
    }
    
    /**
     * Get the list of all source names.
     * 
     * @return array<string> List of source names
     */
    public function getSourceNames(): array
    {
        return array_keys($this->eventsBySource);
    }
    
    /**
     * Clear all stored events.
     */
    public function clearAll(): void
    {
        $this->eventsBySource = [];
        $this->lastEventIds = [];
    }
    
    /**
     * Clear events for a specific source.
     * 
     * @param string $sourceName The source name
     */
    public function clearSource(string $sourceName): void
    {
        unset($this->eventsBySource[$sourceName]);
        unset($this->lastEventIds[$sourceName]);
    }
}