<?php

declare(strict_types=1);

namespace App\EventLoader\Mock;

use App\EventLoader\Interface\RequestTimeManagerInterface;
// use Psr\Log\LoggerInterface;

/**
 * In-memory implementation for managing request times.
 */
class InMemoryRequestTimeManager implements RequestTimeManagerInterface
{
    /** @var array<string, int> Map of source names to last request times */
    private array $lastRequestTimes = [];
    
    /** @var LoggerInterface */
    private LoggerInterface $logger;
    
    /** @var int Minimum interval between requests in milliseconds */
    private int $minimumInterval;
    
    /**
     * @param LoggerInterface $logger Logger for recording operations
     * @param int $minimumInterval Minimum interval between requests in milliseconds
     */
    public function __construct(LoggerInterface $logger, int $minimumInterval = 200)
    {
        $this->logger = $logger;
        $this->minimumInterval = $minimumInterval;
    }
    
    /**
     * Record the time of a request to a source.
     * 
     * @param string $sourceName The source name
     */
    public function setLastRequestTime(string $sourceName, int $timestamp): void
    {
        $this->lastRequestTimes[$sourceName] = $timestamp;
        $this->logger->debug("Recorded request time for source {$sourceName}: {$timestamp} ms");
    }
    
    /**
     * Get the time of the last request to a source.
     * 
     * @param string $sourceName The source name
     * @return int|null The last request time in milliseconds, or null if no requests have been made
     */
    public function getLastRequestTime(string $sourceName): ?int
    {
        return $this->lastRequestTimes[$sourceName] ?? null;
    }
    
    /**
     * Wait until the minimum interval has passed since the last request to the source.
     * 
     * @param string $sourceName The source name
     */
    public function waitForMinimumInterval(string $sourceName, int $minimumIntervalMs): void
    {
        $lastRequestTime = $this->getLastRequestTime($sourceName);
        
        if ($lastRequestTime === null) {
            return;
        }
        
        $currentTime = $this->getCurrentTimeMs();
        $elapsedTime = $currentTime - $lastRequestTime;
        
        if ($elapsedTime < $minimumIntervalMs) {
            $sleepTime = $minimumIntervalMs - $elapsedTime;
            usleep($sleepTime * 1000); // Convert to microseconds
        }
    }
    
    /**
     * Get the current time in milliseconds.
     */
    private function getCurrentTimeMs(): int
    {
        return (int) (microtime(true) * 1000);
    }
    
    /**
     * Set the minimum interval between requests.
     * 
     * @param int $minimumInterval Minimum interval in milliseconds
     */
    public function setMinimumInterval(int $minimumInterval): void
    {
        $this->minimumInterval = $minimumInterval;
    }
    
    /**
     * Get the minimum interval between requests.
     * 
     * @return int Minimum interval in milliseconds
     */
    public function getMinimumInterval(): int
    {
        return $this->minimumInterval;
    }
    
    /**
     * Clear all recorded request times.
     */
    public function clearAllRequestTimes(): void
    {
        $this->lastRequestTimes = [];
        $this->logger->debug("Cleared all request times");
    }
}