<?php

declare(strict_types=1);

namespace App\EventLoader\Source;

use App\EventLoader\Interface\EventSourceInterface;
use App\EventLoader\Mock\LoggerInterface;
// use Psr\Log\LoggerInterface;

class UrlEventSource implements EventSourceInterface
{
    private string $name;
    private string $url;
    private LoggerInterface $logger;
    private array $options;

    /**
     * @param string $name Unique name for this event source
     * @param string $url Base URL for the event source API
     * @param LoggerInterface $logger Logger for recording operations
     * @param array $options Additional options for HTTP requests
     */
    public function __construct(
        string $name,
        string $url,
        LoggerInterface $logger,
        array $options = []
    ) {
        $this->name = $name;
        $this->url = rtrim($url, '/');
        $this->logger = $logger;
        $this->options = $options;
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function fetchEvents(?int $lastEventId = null): array
    {
        $url = $this->buildUrl($lastEventId);
        $this->logger->debug("Fetching events from URL: {$url}");
        
        try {
            $response = $this->makeRequest($url);
            
            if (!$response) {
                $this->logger->error("Failed to get response from {$url}");
                return [];
            }
            
            $events = $this->parseResponse($response);
            $this->logger->debug("Fetched " . count($events) . " events from {$url}");
            
            return $events;
            
        } catch (\Exception $e) {
            $this->logger->error("Error fetching events from {$url}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Build the URL for fetching events, including query parameters
     */
    private function buildUrl(?int $lastEventId): string
    {
        $url = $this->url;
        
        // Add the last event ID as a query parameter if provided
        if ($lastEventId !== null) {
            $separator = (strpos($url, '?') === false) ? '?' : '&';
            $url .= $separator . 'lastEventId=' . $lastEventId;
        }
        
        return $url;
    }

    /**
     * Make an HTTP request to the specified URL
     */
    private function makeRequest(string $url): ?string
    {
        $ch = curl_init($url);
        
        // Set default cURL options
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_USERAGENT => 'EventLoader/1.0',
        ]);
        
        // Add any custom options
        if (!empty($this->options['headers'])) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $this->options['headers']);
        }
        
        if (!empty($this->options['auth'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->options['auth']);
        }
        
        // Execute the request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        // Check for errors
        if ($response === false) {
            $error = curl_error($ch);
            $this->logger->error("cURL error: {$error}");
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        // Check for successful HTTP status code
        if ($httpCode < 200 || $httpCode >= 300) {
            $this->logger->error("HTTP error: {$httpCode}, Response: {$response}");
            return null;
        }
        
        return $response;
    }

    /**
     * Parse the response from the event source
     */
    private function parseResponse(string $response): array
    {
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger->error("JSON parse error: " . json_last_error_msg());
            return [];
        }
        
        // Ensure the response is an array of events
        if (!is_array($data)) {
            $this->logger->error("Invalid response format: expected array, got " . gettype($data));
            return [];
        }
        
        // Ensure each event has the required fields
        $events = [];
        foreach ($data as $event) {
            if (!isset($event['id'])) {
                $this->logger->warning("Skipping event without ID");
                continue;
            }
            
            // Add the source name to each event
            $event['source'] = $this->name;
            $events[] = $event;
        }
        
        return $events;
    }
}