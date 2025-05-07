<?php

declare(strict_types=1);

namespace App\EventLoader\Interface;

/**
 * Interface for managing locks to ensure parallel execution safety.
 */
interface LockManagerInterface
{
    /**
     * Acquire a lock for a specific source.
     * 
     * @param string $sourceName The name of the source to lock
     * @param int $ttl Time to live for the lock in seconds
     * @return bool True if lock was acquired, false otherwise
     */
    public function acquireLock(string $sourceName, int $ttl = 30): bool;
    
    /**
     * Release a lock for a specific source.
     * 
     * @param string $sourceName The name of the source to unlock
     * @return bool True if lock was released, false otherwise
     */
    public function releaseLock(string $sourceName): bool;
}