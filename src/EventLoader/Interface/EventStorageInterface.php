<?php

declare(strict_types=1);

namespace App\EventLoader\Interface;

/**
 * Interface for storing events in a database.
 */
interface EventStorageInterface
{
    /**
     * Store multiple events in the database.
     * 
     * @param array<EventInterface> $events The events to store
     */
    public function storeEvents(array $events): void;
    
    /**
     * Get the ID of the last event stored for a specific source.
     * 
     * @param string $sourceName The name of the source
     * @return int|null The ID of the last event or null if no events exist
     */
    public function getLastEventId(string $sourceName): ?int;
}