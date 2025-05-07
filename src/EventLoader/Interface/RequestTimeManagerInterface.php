<?php
declare(strict_types=1);
namespace App\EventLoader\Interface;
/**
 * Interface for managing request times to avoid rate limiting.
 */
// This interface is used to manage the timing of requests to external sources. It helps in tracking the last request time for each source and ensuring that requests are made at a minimum interval to avoid hitting rate limits.
// It provides methods to get the last request time, set the last request time, and wait for a minimum interval before making the next request.


interface RequestTimeManagerInterface
{
    public function getLastRequestTime(string $sourceName): ?int;
    public function setLastRequestTime(string $sourceName, int $timestamp): void;
    public function waitForMinimumInterval(string $sourceName, int $minimumIntervalMs): void;
}