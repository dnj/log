<?php

namespace dnj\Log;

use dnj\Filesystem\Local\File;
use Exception;
use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;

/**
 * @phpstan-type LineComponents array{date:string,pid:string,level:string,generation:string,message:string}
 */
class Logger extends AbstractLogger
{
    public const LOG_LEVELS = [
        LogLevel::EMERGENCY,
        LogLevel::ALERT,
        LogLevel::CRITICAL,
        LogLevel::ERROR,
        LogLevel::WARNING,
        LogLevel::NOTICE,
        LogLevel::INFO,
        LogLevel::DEBUG,
    ];
    protected const LEVELS_COLORS = [
        [41, 91], // LogLevel::EMERGENCY
        [41, 91], // LogLevel::ALERT
        [41, 91], // LogLevel::CRITICAL
        [45, 95], // LogLevel::ERROR
        [43, 93], // LogLevel::WARNING
        [42, 92], // LogLevel::NOTICE
        [42, 92], // LogLevel::INFO
        [46, 96], // LogLevel::INFO
    ];

    /**
     * @var File|null
     */
    protected $file = null;

    /**
     * @var int
     */
    protected $generation = 0;

    /**
     * @var string
     */
    protected $indentation = "\t";

    /**
     * @var int|null
     */
    protected $level = null;

    /**
     * @var bool
     */
    protected $quiet = true;

    /**
     * @var array{"level":mixed,"message":string}|null
     */
    protected $lastLog = null;

    public function getInstance(): self
    {
        $child = new self();
        $child->file = $this->file;
        $child->generation = $this->generation + 1;
        $child->indentation = $this->indentation;
        $child->level = $this->level;
        $child->quiet = $this->quiet;

        return $child;
    }

    public function setFile(?File $file): void
    {
        $this->file = $file;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    /**
     * @param mixed $level
     */
    public function setLevel($level): void
    {
        $index = array_search($level, self::LOG_LEVELS);
        if (false === $index) {
            throw new InvalidArgumentException('level is invalid');
        }
        $this->level = $index;
    }

    /**
     * @return mixed
     */
    public function getLevel()
    {
        return self::LOG_LEVELS[$this->level] ?? null;
    }

    public function setIndentation(string $indentation, int $repeat = 1): void
    {
        $this->indentation = str_repeat($indentation, $repeat);
    }

    public function getIndentation(): string
    {
        return $this->indentation;
    }

    public function getGeneration(): int
    {
        return $this->generation;
    }

    public function setQuiet(bool $quiet): void
    {
        $this->quiet = $quiet;
    }

    public function isQuiet(): bool
    {
        return $this->quiet;
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function append($message, $context = []): void
    {
        if (null === $this->lastLog) {
            throw new Exception();
        }
        $message = $this->makeMessage($message, $context);
        $this->log($this->lastLog['level'], $this->lastLog['message'].$message);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function reply($message, $context = []): void
    {
        if (null === $this->lastLog) {
            throw new Exception();
        }
        $message = $this->makeMessage($message, $context);
        $this->log($this->lastLog['level'], $this->lastLog['message'].':'.$message);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    public function log($level, $message, $context = []): void
    {
        $levelIndex = array_search($level, self::LOG_LEVELS);
        if (false === $levelIndex) {
            throw new InvalidArgumentException('level is invalid');
        }
        $message = $this->makeMessage($message, $context);
        $this->lastLog = [
            'level' => $level,
            'message' => $message,
        ];
        if ($levelIndex > $this->level) {
            return;
        }
        if (!is_string($level)) {
            throw new Exception();
        }
        $components = $this->makeLineComponents($level, $message);
        $plainLine = $this->makePlainLine($components).PHP_EOL;
        if ($this->file) {
            $this->file->append($plainLine);
        }
        if ($this->quiet) {
            return;
        }
        if (PHP_SAPI != 'cli') {
            echo $plainLine;

            return;
        }
        $buffer = in_array($level, [LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL, LogLevel::ERROR]) ? STDERR : STDOUT;
        if (!stream_isatty($buffer)) {
            echo $plainLine;

            return;
        }
        fwrite($buffer, $this->makeColoredLine($levelIndex, $components).PHP_EOL);
    }

    /**
     * @param string  $message
     * @param mixed[] $context
     */
    protected function makeMessage($message, $context = []): string
    {
        if (!is_string($message)) {
            $message = $message->__toString();
        }
        if ($context) {
            $message .= ' Context: '.json_encode($context);
        }

        return $message;
    }

    /**
     * @return LineComponents
     */
    protected function makeLineComponents(string $level, string $message): array
    {
        $microtime = explode(' ', microtime());
        $date = date('Y-m-d H:i:s.'.substr($microtime[0], 2).' P');
        $pid = PHP_SAPI == 'cli' ? '['.getmypid().']' : '';
        $level = '['.strtoupper($level).']';
        $generation = str_repeat($this->indentation, $this->generation);

        return [
            'date' => $date,
            'pid' => $pid,
            'level' => $level,
            'generation' => $generation,
            'message' => $message,
        ];
    }

    /**
     * @param LineComponents $components
     */
    protected function makePlainLine(array $components): string
    {
        $components = array_filter($components, 'strlen');

        return implode(' ', $components);
    }

    /**
     * @param LineComponents $components
     */
    protected function makeColoredLine(int $levelIndex, array $components): string
    {
        $components['level'] = "\033[".self::LEVELS_COLORS[$levelIndex][0].'m'.$components['level']."\033[0m";
        $components['message'] = "\033[".self::LEVELS_COLORS[$levelIndex][1].'m'.$components['message']."\033[0m";
        $components = array_filter($components, 'strlen');

        return implode(' ', $components);
    }
}
