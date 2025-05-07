<?php

declare(strict_types=1);

namespace App\EventLoader\Infrastructure;

use PDO;
use PDOException;
use App\EventLoader\Mock\LoggerInterface;
// use Psr\Log\LoggerInterface;
use App\EventLoader\Interface\RequestTimeManagerInterface;

class RequestTimeManager implements RequestTimeManagerInterface
{
    private PDO $pdo;
    private LoggerInterface $logger;
    private array $cache = [];

    public function __construct(PDO $pdo, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        
        // Ensure the request_times table exists
        $this->initializeTable();
    }

    /**
     * Create the request_times table if it doesn't exist
     */
    private function initializeTable(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS request_times (
                    source_name VARCHAR(255) PRIMARY KEY,
                    last_request_time BIGINT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )
            ");
        } catch (PDOException $e) {
            $this->logger->error("Failed to initialize request_times table: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get the last request time for a source
     */
    public function getLastRequestTime(string $sourceName): ?int
    {
        // Check cache first
        if (isset($this->cache[$sourceName])) {
            return $this->cache[$sourceName];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT last_request_time FROM request_times
                WHERE source_name = :source_name
            ");
            
            $stmt->execute([':source_name' => $sourceName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $this->cache[$sourceName] = (int)$result['last_request_time'];
                return $this->cache[$sourceName];
            }
            
            return null;
            
        } catch (PDOException $e) {
            $this->logger->error("Error retrieving last request time for {$sourceName}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Set the last request time for a source
     */
    public function setLastRequestTime(string $sourceName, int $timestamp): void
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO request_times (source_name, last_request_time)
                VALUES (:source_name, :last_request_time)
                ON DUPLICATE KEY UPDATE
                    last_request_time = :last_request_time
            ");
            
            $stmt->execute([
                ':source_name' => $sourceName,
                ':last_request_time' => $timestamp
            ]);
            
            // Update cache
            $this->cache[$sourceName] = $timestamp;
            
        } catch (PDOException $e) {
            $this->logger->error("Error setting last request time for {$sourceName}: " . $e->getMessage());
        }
    }

    /**
     * Wait until the minimum interval has passed since the last request to the source.
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
}