<?php

declare(strict_types=1);

namespace App\EventLoader\Interface;

/**
 * Interface for retrieving events from a remote source.
 */
interface EventSourceInterface
{
    /**
     * Get the unique name of this event source.
     */
    public function getName(): string;
    
    /**
     * Fetch events from the source with ID greater than the last known ID.
     * 
     * @param int|null $lastKnownId The ID of the last event that was processed
     * @return array<EventInterface> Array of events, sorted by ID
     * @throws \Exception If there's an error fetching events
     */
    public function fetchEvents(?int $lastKnownId = null): array;
}