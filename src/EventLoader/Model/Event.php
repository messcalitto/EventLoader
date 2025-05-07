<?php

declare(strict_types=1);

namespace App\EventLoader\Model;

use App\EventLoader\Interface\EventInterface;

class Event implements EventInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $sourceName,
        private readonly mixed $data
    ) {
    }
    
    public function getId(): int
    {
        return $this->id;
    }
    
    public function getSourceName(): string
    {
        return $this->sourceName;
    }
    
    public function getData(): mixed
    {
        return $this->data;
    }
}