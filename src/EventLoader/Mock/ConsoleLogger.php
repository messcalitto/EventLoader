<?php

declare(strict_types=1);

namespace App\EventLoader\Mock;


interface LoggerInterface
{
    public function log(string|\Stringable $message, array $context = []): void;
    public function warning(string|\Stringable $message, array $context = []): void;
    public function error(string|\Stringable $message, array $context = []): void;
    public function info(string|\Stringable $message, array $context = []): void;
    public function debug(string|\Stringable $message, array $context = []): void;
}


/**
 * Simple console logger that implements PSR-3 LoggerInterface.
 */
class ConsoleLogger implements LoggerInterface
{
    private bool $debug;
    
    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }
    
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function log(string|\Stringable $message, array $context = []): void
    {
        // Skip debug messages unless debug mode is enabled
        // if ($level === LogLevel::DEBUG && !$this->debug) {
        //     return;
        // }
        
        // Format the message with context
        $formattedMessage = $this->interpolate((string)$message, $context);
        
        // Format the log level

        
        // Get timestamp
        $timestamp = date('Y-m-d H:i:s');
        
        // Output to console
        echo "[{$timestamp}] {$formattedMessage}" . PHP_EOL;
    }
    
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log( $message, $context);
    }
    
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log( $message, $context);
    }
    
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log( $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log( $message, $context);
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param string $message
     * @param array $context
     * @return string
     */
    private function interpolate(string $message, array $context = []): string
    {
        // Build a replacement array with braces around the context keys
        $replace = [];
        foreach ($context as $key => $val) {
            // Check if the value can be cast to string
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }
        
        // Interpolate replacement values into the message and return
        return strtr($message, $replace);
    }
    
    /**
     * Enable or disable debug logging.
     *
     * @param bool $debug
     * @return void
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }
}