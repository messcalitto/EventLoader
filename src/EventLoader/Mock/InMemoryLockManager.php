<?php

declare(strict_types=1);

namespace App\EventLoader\Mock;

use App\EventLoader\Interface\LockManagerInterface;
use App\EventLoader\Mock\LoggerInterface;
// use Psr\Log\LoggerInterface;

/**
 * In-memory implementation of LockManagerInterface for testing purposes.
 */
class InMemoryLockManager implements LockManagerInterface
{
    /** @var array<string, bool> Map of source names to lock status */
    private array $locks = [];
    
    /** @var array<string, int> Map of source names to lock expiration times */
    private array $lockExpirations = [];
    
    /** @var LoggerInterface */
    private LoggerInterface $logger;
    
    /**
     * @param LoggerInterface $logger Logger for recording operations
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
    
    /**
     * {@inheritdoc}
     */
    public function acquireLock(string $sourceName, int $ttl = 30): bool
    {
        // Check if the lock exists and is not expired
        if (isset($this->locks[$sourceName]) && $this->locks[$sourceName]) {
            $expirationTime = $this->lockExpirations[$sourceName] ?? 0;
            $currentTime = time();
            
            // If the lock has expired, we can acquire it
            if ($currentTime > $expirationTime) {
                $this->logger->log("Lock for source {$sourceName} has expired, acquiring new lock");
                $this->releaseLock($sourceName);
            } else {
                $this->logger->log("Lock for source {$sourceName} is already held");
                return false;
            }
        }
        
        // Acquire the lock
        $this->locks[$sourceName] = true;
        $this->lockExpirations[$sourceName] = time() + $ttl;
        $this->logger->log("Acquired lock for source {$sourceName} with TTL of {$ttl} seconds");
        
        return true;
    }
    
    /**
     * {@inheritdoc}
     */
    public function releaseLock(string $sourceName): bool
    {
        $wasLocked = isset($this->locks[$sourceName]) && $this->locks[$sourceName];
        
        $this->locks[$sourceName] = false;
        unset($this->lockExpirations[$sourceName]);
        
        if ($wasLocked) {
            $this->logger->log("Released lock for source {$sourceName}");
        } else {
            $this->logger->log("Attempted to release non-existent lock for source {$sourceName}");
        }
        
        return $wasLocked;
    }
    
    /**
     * Check if a lock is currently held for a source.
     * 
     * @param string $sourceName The source name
     * @return bool True if the lock is held, false otherwise
     */
    public function isLocked(string $sourceName): bool
    {
        if (!isset($this->locks[$sourceName]) || !$this->locks[$sourceName]) {
            return false;
        }
        
        $expirationTime = $this->lockExpirations[$sourceName] ?? 0;
        $currentTime = time();
        
        // If the lock has expired, it's not considered locked
        if ($currentTime > $expirationTime) {
            $this->releaseLock($sourceName);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get the remaining lock time in seconds.
     * 
     * @param string $sourceName The source name
     * @return int|null Remaining lock time in seconds, or null if not locked
     */
    public function getLockRemainingTime(string $sourceName): ?int
    {
        if (!$this->isLocked($sourceName)) {
            return null;
        }
        
        $expirationTime = $this->lockExpirations[$sourceName];
        $currentTime = time();
        
        return max(0, $expirationTime - $currentTime);
    }
    
    /**
     * Clear all locks.
     */
    public function clearAllLocks(): void
    {
        $this->locks = [];
        $this->lockExpirations = [];
        $this->logger->log("Cleared all locks");
    }
}