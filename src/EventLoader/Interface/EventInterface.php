<?php

declare(strict_types=1);

namespace App\EventLoader\Interface;

/**
 * Interface representing an event from a source.
 */
interface EventInterface
{
    /**
     * Get the unique identifier of the event.
     */
    public function getId(): int;
    
    /**
     * Get the source name this event came from.
     */
    public function getSourceName(): string;
    
    /**
     * Get the event data.
     * 
     * @return mixed The event data
     */
    public function getData(): mixed;
}