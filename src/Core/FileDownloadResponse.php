<?php

namespace PayOS\Core;

/**
 * File Download Response structure
 */
class FileDownloadResponse
{
    public ?string $filename;
    public string $contentType;
    public ?int $size;
    public string $data;

    public function __construct(?string $filename, string $contentType, ?int $size, string $data)
    {
        $this->filename = $filename;
        $this->contentType = $contentType;
        $this->size = $size;
        $this->data = $data;
    }

    /**
     * Save the file data to disk
     *
     * @param string $path Full path where the file should be saved. If it ends with a directory
     *                     separator or is an existing directory, the filename from the response will be used.
     * @return string The full path where the file was saved
     * @throws \RuntimeException If the file cannot be written
     */
    public function saveToFile(string $path): string
    {
        $isDirectory = is_dir($path) || str_ends_with($path, '/') || str_ends_with($path, DIRECTORY_SEPARATOR);

        if ($isDirectory) {
            if (!$this->filename) {
                throw new \RuntimeException('No filename available in response and path is a directory');
            }
            $path = rtrim($path, '/\\') . DIRECTORY_SEPARATOR . $this->filename;
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0o755, true) && !is_dir($directory)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }

        $result = file_put_contents($path, $this->data);

        if ($result === false) {
            throw new \RuntimeException("Failed to write file: {$path}");
        }

        return $path;
    }

    /**
     * Get the file extension from the filename
     *
     * @return string|null The file extension without the dot, or null if no filename
     */
    public function getExtension(): ?string
    {
        if (!$this->filename) {
            return null;
        }

        $extension = pathinfo($this->filename, PATHINFO_EXTENSION);

        return $extension ?: null;
    }

    /**
     * Get the filename without extension
     *
     * @return string|null The filename without extension, or null if no filename
     */
    public function getBasename(): ?string
    {
        if (!$this->filename) {
            return null;
        }

        return pathinfo($this->filename, PATHINFO_FILENAME);
    }
}
