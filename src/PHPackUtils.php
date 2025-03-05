<?php

declare(strict_types=1);

namespace RPurinton\PHPack;

use RuntimeException;

class PHPackUtils
{
    public static function formatSize(int $size): string
    {
        if ($size >= 1048576) {
            return round($size / 1048576, 2) . ' MiB';
        } elseif ($size >= 1024) {
            return round($size / 1024, 2) . ' KiB';
        } else {
            return $size . ' bytes';
        }
    }

    public static function getJsonData(string $jsonFile): array
    {
        $jsonContent = file_get_contents($jsonFile);
        if ($jsonContent === false) {
            throw new RuntimeException("Failed to read file: $jsonFile");
        }
        $data = json_decode($jsonContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in file: $jsonFile. Error: " . json_last_error_msg());
        }
        return $data;
    }

    public static function checkJsonStructure(array $data, string $jsonFile): void
    {
        if ($data === null) {
            throw new RuntimeException("Empty or invalid JSON structure in file: $jsonFile.");
        }
        if (!isset($data['parts']) || !is_array($data['parts'])) {
            throw new RuntimeException("Invalid structure in file: $jsonFile. 'parts' key is missing or not an array.");
        }
        if (empty($data['parts'])) {
            throw new RuntimeException("No parts found in JSON file: $jsonFile.");
        }
    }

    public static function isRawCode(string $part): bool
    {
        return strpos($part, '#') === 0;
    }

    public static function createDirectoryIfNotExists(string $dir): void
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
                throw new RuntimeException("Failed to create directory: " . $dir);
            }
        }
    }

    public static function writeToFile(string $filePath, string $content): void
    {
        if (file_put_contents($filePath, $content) === false) {
            throw new RuntimeException("Failed to write to file: $filePath");
        }
    }

    public static function fixPhpTags(string $content): string
    {
        return $content . ((substr_count($content, '<?') > substr_count($content, '?>')) ? ' ?>' : '');
    }

    public static function minifyHtml(string $html): string
    {
        $blockPattern = '#(?P<blk><(script|pre|textarea)\b[^>]*>.*?</\2>)#si';
        $blocks = [];
        $blockIndex = 0;

        $html = preg_replace_callback($blockPattern, function ($matches) use (&$blocks, &$blockIndex) {
            $placeholder = "@@HTML_BLOCK_$blockIndex@@";
            $blocks[$placeholder] = $matches[0];
            $blockIndex++;
            return $placeholder;
        }, $html);

        $html = preg_replace('/<!--(?!\s*\[if).*?-->/', '', $html);
        $html = preg_replace('/\s+/', ' ', $html);

        return str_replace(array_keys($blocks), array_values($blocks), $html);
    }
}
