<?php

declare(strict_types=1);

namespace RPurinton\PHPack;

use RPurinton\PHPack\PHPackUtils;
use RuntimeException;
use Throwable;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

class PHPack
{
    private string $pagesDir;
    private string $partsDir;
    private string $publicDir;

    private array $report = [
        'processed' => [],
        'errors' => [],
    ];

    public function __construct()
    {
        set_exception_handler([$this, 'exceptionHandler']);
        $currentDir = getcwd();
        [$this->pagesDir, $this->partsDir, $this->publicDir] = ["$currentDir/pages", "$currentDir/parts", "$currentDir/public"];
        if (!is_dir($this->pagesDir) || !is_dir($this->partsDir)) {
            throw new RuntimeException("Missing folder");
        }
    }

    public function processFiles(): void
    {
        $this->validateJsonFiles();
        if (!empty($this->report['errors'])) {
            $this->outputReport();
            exit(1);
        }

        foreach ($this->getJsonFiles() as $jsonFile) {
            try {
                $outputFile = $this->getOutputFilePath($jsonFile);
                PHPackUtils::createDirectoryIfNotExists(dirname($outputFile));
                $content = $this->generateContent($this->parseJsonFile($jsonFile, $this->partsDir));
                PHPackUtils::writeToFile($outputFile, $content);
                $this->report['processed'][] = [
                    'input' => $jsonFile,
                    'output' => $outputFile,
                    'size' => filesize($outputFile),
                ];
            } catch (RuntimeException $e) {
                $this->report['errors'][] = [
                    'file' => $jsonFile,
                    'error' => $e->getMessage(),
                ];
            }
        }
        $this->outputReport();
    }

    private function outputReport(): void
    {
        echo "PHPack Report\n";
        echo "=============\n\n";

        echo "Processed Files:\n";
        foreach ($this->report['processed'] as $file) {
            echo "  - Input: {$file['input']}\n";
            echo "    Output: {$file['output']}\n";
            echo "    Size: " . PHPackUtils::formatSize($file['size']) . "\n";
        }

        if (!empty($this->report['errors'])) {
            echo "\nErrors:\n";
            foreach ($this->report['errors'] as $error) {
                echo "  - File: {$error['file']}\n";
                echo "    Error: {$error['error']}\n";
            }
        }

        echo "\nSummary:\n";
        echo "  Total files processed: " . count($this->report['processed']) . "\n";
        echo "  Total errors: " . count($this->report['errors']) . "\n";
    }

    private function validateJsonFiles(): void
    {
        foreach ($this->getJsonFiles() as $jsonFile) {
            try {
                $this->validateJsonFile($jsonFile);
            } catch (RuntimeException $e) {
                $this->report['errors'][] = [
                    'file' => $jsonFile,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    private function validateJsonFile(string $jsonFile, ?string $baseDir = null, array $stack = []): void
    {
        $data = PHPackUtils::getJsonData($jsonFile);
        PHPackUtils::checkJsonStructure($data, $jsonFile);

        $baseDir = $baseDir ?: $this->partsDir;
        $partsDirRealPath = realpath($this->partsDir);
        $jsonFileRealPath = realpath($jsonFile);
        $workingDir = (strpos($jsonFileRealPath, $partsDirRealPath) === 0) ? dirname($jsonFile) : $baseDir;

        foreach ($data['parts'] as $part) {
            $this->validatePart($part, $jsonFile, $workingDir, $jsonFileRealPath, $stack);
        }
    }

    private function validatePart(string $part, string $jsonFile, string $workingDir, string $jsonFileRealPath, array $stack = []): void
    {
        try {
            if (PHPackUtils::isRawCode($part)) {
                return;
            }
            $partPath = realpath($workingDir . DIRECTORY_SEPARATOR . ltrim($part, '#'));
            if (!$partPath || !file_exists($partPath)) {
                $stack[] = "$jsonFile part[" . array_search($part, PHPackUtils::getJsonData($jsonFile)['parts']) . "] ($part)";
                $this->report['errors'][] = [
                    'file' => $jsonFile,
                    'error' => "Part file does not exist $partPath at " . implode(' -> ', $stack),
                ];
                return;
            }
            if (strtolower(pathinfo($part, PATHINFO_EXTENSION)) === 'json') {
                if ($partPath === $jsonFileRealPath) {
                    $this->report['errors'][] = [
                        'file' => $jsonFile,
                        'error' => "Recursive JSON reference detected in file: $jsonFile.",
                    ];
                    return;
                }
                $stack[] = "$jsonFile part[" . array_search($part, PHPackUtils::getJsonData($jsonFile)['parts']) . "] ($part)";
                $this->validateJsonFile($partPath, $workingDir, $stack);
            } else {
                if (filesize($partPath) === 0) {
                    $this->report['errors'][] = [
                        'file' => $jsonFile,
                        'error' => "Part file is empty: $partPath",
                    ];
                }
            }
        } catch (RuntimeException $e) {
            $this->report['errors'][] = [
                'file' => $jsonFile,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function getJsonFiles(): array
    {
        $jsonFiles = [];
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->pagesDir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && strtolower($file->getExtension()) === 'json') {
                    $jsonFiles[] = $file->getPathname();
                }
            }
        } catch (UnexpectedValueException $e) {
            throw new RuntimeException("Failed to iterate directory: " . $e->getMessage());
        }
        return $jsonFiles;
    }

    private function getOutputFilePath(string $jsonFile): string
    {
        $relativePath = str_replace(realpath($this->pagesDir), '', realpath($jsonFile));
        return $this->publicDir . dirname($relativePath) . DIRECTORY_SEPARATOR . pathinfo($jsonFile, PATHINFO_FILENAME) . '.php';
    }

    private function parseJsonFile(string $jsonFile, ?string $baseDir = null): string
    {
        $content = '';
        $baseDir = $baseDir ?: $this->partsDir;
        $parts = PHPackUtils::getJsonData($jsonFile)['parts'];
        $workingDir = $this->getWorkingDir($jsonFile, $baseDir);

        foreach ($parts as $part) {
            $content .= $this->getPartContent($part, $workingDir);
        }
        return $content;
    }

    private function getWorkingDir(string $jsonFile, string $baseDir): string
    {
        $partsDirRealPath = realpath($this->partsDir);
        $jsonFileRealPath = realpath($jsonFile);
        return (strpos($jsonFileRealPath, $partsDirRealPath) === 0) ? dirname($jsonFile) : $baseDir;
    }

    private function getPartContent(string $part, string $workingDir): string
    {
        if (PHPackUtils::isRawCode($part)) {
            return ltrim($part, '#');
        }
        $extension = strtolower(pathinfo($part, PATHINFO_EXTENSION));
        $partPath = "$workingDir/" . ltrim($part, '#');
        if (!file_exists($partPath)) {
            throw new RuntimeException("Part file does not exist: $partPath");
        }
        if (!in_array($extension, ['html', 'php', 'json'])) {
            return $part;
        }
        $partContent = file_get_contents($partPath);
        if ($partContent === false) {
            throw new RuntimeException("Failed to read part file: $partPath");
        }
        return ($extension === 'json') ? $this->parseJsonFile($partPath, $workingDir) : PHPackUtils::fixPhpTags($partContent);
    }

    private function generateContent(string $content): string
    {
        $shebang = "";
        if (strpos($content, "#!") === 0) {
            $newlinePos = strpos($content, "\n");
            if ($newlinePos !== false) {
                $shebang = substr($content, 0, $newlinePos);
                $content = substr($content, $newlinePos + 1);
            }
        }

        $tokens = token_get_all($content);
        $output = "";
        $whitespace = false;

        foreach ($tokens as $token) {
            if (is_string($token)) {
                $output .= $token;
                $whitespace = false;
                continue;
            }

            [$tokenType, $tokenValue] = $token;
            if ($tokenType === T_COMMENT || $tokenType === T_DOC_COMMENT) continue;
            elseif ($tokenType === T_WHITESPACE) {
                if (!$whitespace) {
                    $output .= " ";
                    $whitespace = true;
                }
            } elseif ($tokenType === T_INLINE_HTML) {
                $output .= PHPackUtils::minifyHtml($tokenValue);
                $whitespace = false;
            } elseif ($tokenType === T_OPEN_TAG || $tokenType === T_OPEN_TAG_WITH_ECHO) {
                $output .= rtrim($tokenValue, "\r\n") . " ";
                $whitespace = false;
            } else {
                $output .= $tokenValue;
                $whitespace = false;
            }
        }

        $output = trim($output);
        $output = preg_replace('/\?>\s*<\?php\s*/', ' ', $output);
        if ($shebang !== "") $output = $shebang . "\n" . $output;

        return $output;
    }

    private function exceptionHandler(Throwable $e): void
    {
        $this->report['errors'][] = [
            'file' => 'Unknown',
            'error' => $e->getMessage(),
        ];
        $this->outputReport();
        exit(1);
    }
}
