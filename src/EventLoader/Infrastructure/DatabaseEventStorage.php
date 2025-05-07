<?php

declare(strict_types=1);

namespace App\EventLoader\Infrastructure;

use App\EventLoader\Interface\EventInterface;
use App\EventLoader\Interface\EventStorageInterface;
use PDO;

class DatabaseEventStorage implements EventStorageInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }
    
    public function storeEvents(array $events): void
    {
        if (empty($events)) {
            return;
        }
        
        $this->pdo->beginTransaction();
        
        try {
            $stmt = $this->pdo->prepare('
                INSERT INTO events (id, source_name, data, created_at)
                VALUES (:id, :source_name, :data, :created_at)
            ');
            
            foreach ($events as $event) {
                if (!$event instanceof EventInterface) {
                    throw new \InvalidArgumentException('Event must implement EventInterface');
                }
                
                $stmt->execute([
                    'id' => $event->getId(),
                    'source_name' => $event->getSourceName(),
                    'data' => json_encode($event->getData()),
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
            
            $this->pdo->commit();
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function getLastEventId(string $sourceName): ?int
    {
        $stmt = $this->pdo->prepare('
            SELECT MAX(id) as last_id
            FROM events
            WHERE source_name = :source_name
        ');
        
        $stmt->execute(['source_name' => $sourceName]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['last_id'] ? (int)$result['last_id'] : null;
    }

    public function getAllEvents(int $limit = 10): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, source_name, data, created_at
            FROM events
            ORDER BY created_at DESC
            LIMIT :limit
        ');
        
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}