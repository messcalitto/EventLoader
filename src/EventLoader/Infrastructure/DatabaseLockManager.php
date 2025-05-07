<?php

declare(strict_types=1);

namespace App\EventLoader\Infrastructure;

use App\EventLoader\Interface\LockManagerInterface;
use PDO;
use App\EventLoader\Mock\LoggerInterface;


class DatabaseLockManager implements LockManagerInterface
{
    private PDO $pdo;
    private LoggerInterface $logger;
    
    public function __construct(PDO $pdo, LoggerInterface $logger)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        
        // Ensure the locks table exists
        $this->initializeTable();
    }
    
    /**
     * {@inheritdoc}
     */
    public function acquireLock(string $sourceName, int $ttl = 30): bool
    {
        // First, check if the resource is already locked
        if ($this->isLocked($sourceName)) {
            $this->logger->debug("Resource '{$sourceName}' is already locked");
            return false;
        }
        
        // Clean up expired locks before attempting to acquire a new one
        $this->cleanupExpiredLocks();
        
        // Calculate expiration time
        $expiresAt = time() + $ttl;
        
        try {
            // Begin transaction to ensure atomicity
            $this->pdo->beginTransaction();
            
            // Double-check if the resource is locked (in case it was locked between our check and transaction)
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM locks 
                WHERE resource_name = :resource_name 
                AND expires_at > :current_time
                LIMIT 1
            ");
            
            $currentTime = time();
            $stmt->bindParam(':resource_name', $sourceName, PDO::PARAM_STR);
            $stmt->bindParam(':current_time', $currentTime, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->fetchColumn()) {
                // Resource is locked by another process
                $this->pdo->rollBack();
                $this->logger->debug("Resource '{$sourceName}' was locked by another process during acquisition");
                return false;
            }
            
            // Delete any existing lock for this resource (expired or not)
            $deleteStmt = $this->pdo->prepare("
                DELETE FROM locks 
                WHERE resource_name = :resource_name
            ");
            $deleteStmt->bindParam(':resource_name', $sourceName, PDO::PARAM_STR);
            $deleteStmt->execute();
            
            // Insert new lock
            $insertStmt = $this->pdo->prepare("
                INSERT INTO locks (resource_name, acquired_at, expires_at) 
                VALUES (:resource_name, :acquired_at, :expires_at)
            ");
            
            $acquiredAt = time();
            $insertStmt->bindParam(':resource_name', $sourceName, PDO::PARAM_STR);
            $insertStmt->bindParam(':acquired_at', $acquiredAt, PDO::PARAM_INT);
            $insertStmt->bindParam(':expires_at', $expiresAt, PDO::PARAM_INT);
            
            $result = $insertStmt->execute();
            
            // Commit the transaction
            $this->pdo->commit();
            
            if ($result) {
                $this->logger->debug("Successfully acquired lock for resource '{$sourceName}' until " . date('Y-m-d H:i:s', $expiresAt));
                return true;
            } else {
                $this->logger->warning("Failed to acquire lock for resource '{$sourceName}'");
                return false;
            }
            
        } catch (\PDOException $e) {
            // Rollback the transaction on error
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            
            $this->logger->error("Database error while acquiring lock for resource '{$sourceName}': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * {@inheritdoc}
     */
    public function releaseLock(string $sourceName): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM locks 
                WHERE resource_name = :resource_name
            ");
            
            $stmt->bindParam(':resource_name', $sourceName, PDO::PARAM_STR);
            $stmt->execute();
            
            $rowCount = $stmt->rowCount();
            
            if ($rowCount > 0) {
                $this->logger->debug("Released lock for resource '{$sourceName}'");
                return true;
            } else {
                $this->logger->debug("No lock found to release for resource '{$sourceName}'");
                return false;
            }
            
        } catch (\PDOException $e) {
            $this->logger->error("Database error while releasing lock for resource '{$sourceName}': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a resource is currently locked.
     *
     * @param string $sourceName The resource name
     * @return bool True if the resource is locked, false otherwise
     */
    public function isLocked(string $sourceName): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 1 FROM locks 
                WHERE resource_name = :resource_name 
                AND expires_at > :current_time
                LIMIT 1
            ");
            
            $currentTime = time();
            $stmt->bindParam(':resource_name', $sourceName, PDO::PARAM_STR);
            $stmt->bindParam(':current_time', $currentTime, PDO::PARAM_INT);
            $stmt->execute();
            
            return (bool) $stmt->fetchColumn();
            
        } catch (\PDOException $e) {
            $this->logger->error("Database error while checking lock for resource '{$sourceName}': " . $e->getMessage());
            // If there's an error, assume it's locked to be safe
            return true;
        }
    }
    
    /**
     * Clean up expired locks from the database.
     */
    private function cleanupExpiredLocks(): void
    {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM locks 
                WHERE expires_at <= :current_time
            ");
            
            $currentTime = time();
            $stmt->bindParam(':current_time', $currentTime, PDO::PARAM_INT);
            $stmt->execute();
            
            $rowCount = $stmt->rowCount();
            if ($rowCount > 0) {
                $this->logger->debug("Cleaned up {$rowCount} expired locks");
            }
            
        } catch (\PDOException $e) {
            $this->logger->error("Database error while cleaning up expired locks: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize the locks table if it doesn't exist.
     */
    private function initializeTable(): void
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS locks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    resource_name VARCHAR(255) NOT NULL,
                    acquired_at INT NOT NULL,
                    expires_at INT NOT NULL,
                    INDEX idx_resource_name (resource_name),
                    INDEX idx_expires_at (expires_at)
                )
            ");
            
        } catch (\PDOException $e) {
            $this->logger->error("Database error while initializing locks table: " . $e->getMessage());
            throw $e; // Re-throw as this is a critical error
        }
    }
}
