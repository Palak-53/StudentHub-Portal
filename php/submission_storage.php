<?php

function sanitize_input($value): string
{
    if (!is_string($value)) {
        return '';
    }

    return trim(stripslashes($value));
}

function escape_html($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function store_submission(array $record): void
{
    $dataDir = __DIR__ . '/../data';
    if (!is_dir($dataDir) && !mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
        throw new RuntimeException('Could not create the submissions directory.');
    }

    $jsonFile = $dataDir . '/submissions.json';
    $file = fopen($jsonFile, 'c+');
    if ($file === false) {
        throw new RuntimeException('Could not open the submissions file.');
    }

    $locked = false;
    try {
        if (!flock($file, LOCK_EX)) {
            throw new RuntimeException('Could not lock the submissions file.');
        }
        $locked = true;

        rewind($file);
        $contents = stream_get_contents($file);
        if ($contents === false) {
            throw new RuntimeException('Could not read the submissions file.');
        }

        $records = [];
        if (trim($contents) !== '') {
            try {
                $records = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $error) {
                throw new RuntimeException('The submissions file contains invalid JSON.', 0, $error);
            }

            if (!is_array($records) || !array_is_list($records)) {
                throw new RuntimeException('The submissions file must contain a JSON array.');
            }
        }

        array_unshift($records, $record);
        try {
            $json = json_encode($records, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Could not encode the submission as JSON.', 0, $error);
        }

        rewind($file);
        if (!ftruncate($file, 0) || fwrite($file, $json . PHP_EOL) !== strlen($json . PHP_EOL) || !fflush($file)) {
            throw new RuntimeException('Could not write the submission to the JSON file.');
        }
    } finally {
        if ($locked) {
            flock($file, LOCK_UN);
        }
        fclose($file);
    }
}
