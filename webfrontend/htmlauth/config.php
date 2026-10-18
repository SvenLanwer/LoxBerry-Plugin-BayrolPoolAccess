<?php
declare(strict_types=1);

/**
 * Bayrol PoolAccess – Konfigurationshelfer
 * Funktioniert lokal im PyCharm-Projekt und kann auf LoxBerry
 * den vom SDK bereitgestellten Plugin-Konfigurationspfad verwenden.
 */

function bayrol_config_file(): string
{
    // LoxBerry: gemeinsame Konfiguration für PHP und Python
    $loxberryConfig =
        '/opt/loxberry/config/plugins/bayrolpoolaccess/config.json';

    if (is_dir('/opt/loxberry/config/plugins')) {
        return $loxberryConfig;
    }

    // Lokales PyCharm-Projekt
    return dirname(__DIR__, 2) . '/config/config.json';
}

function bayrol_read_config(): array
{
    $file = bayrol_config_file();

    if (!is_file($file)) {
        throw new RuntimeException(
            'Konfigurationsdatei nicht gefunden: ' . $file
        );
    }

    $json = file_get_contents($file);
    if ($json === false) {
        throw new RuntimeException('Konfiguration konnte nicht gelesen werden.');
    }

    $config = json_decode($json, true);
    if (!is_array($config)
        || !isset($config['source'], $config['destination'], $config['topics'])
        || !is_array($config['source'])
        || !is_array($config['destination'])
        || !is_array($config['topics'])) {
        throw new RuntimeException('Die Konfiguration ist ungültig.');
    }

    return $config;
}

function bayrol_normalize_config(array $config): array
{
    if (!isset($config['topics']) || !is_array($config['topics'])) {
        throw new RuntimeException('Die Topic-Liste fehlt oder ist ungültig.');
    }

    $topics = $config['topics'];
    $normalized = [];

    // Alte Konfiguration: vollständige Topic-Strings.
    if ($topics !== [] && is_string(reset($topics))) {
        $prefix = '';

        foreach ($topics as $fullTopic) {
            if (
                !is_string($fullTopic)
                || strpos($fullTopic, '/') === false
            ) {
                throw new RuntimeException(
                    'Ungültiger Topic-Eintrag im alten Format.'
                );
            }

            $fullTopic = trim($fullTopic);
            $slash = strrpos($fullTopic, '/');
            $prefix = substr($fullTopic, 0, $slash + 1);
            $id = substr($fullTopic, $slash + 1);

            if ($id === '') {
                throw new RuntimeException('Eine Topic-ID ist leer.');
            }

            $normalized[] = [
                'id' => $id,
                'name' => 'Messwert ' . $id,
                'unit' => '',
                'enabled' => true
            ];
        }

        if (
            !isset($config['source']['topic_prefix'])
            || $config['source']['topic_prefix'] === ''
        ) {
            $config['source']['topic_prefix'] = $prefix;
        }
    } else {
        $seenIds = [];

        foreach ($topics as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('Ungültiger Topic-Eintrag.');
            }

            $id = trim((string)($item['id'] ?? ''));
            $name = trim((string)($item['name'] ?? ''));
            $unit = trim((string)($item['unit'] ?? ''));

            if ($id === '' || $name === '') {
                throw new RuntimeException(
                    'Jeder Messwert benötigt eine Topic-ID und einen Namen.'
                );
            }

            // Erlaubt beispielsweise 4.1 oder 5.76, keine Topic-Pfade.
            if (!preg_match('/^[A-Za-z0-9_.-]+$/', $id)) {
                throw new RuntimeException('Ungültige Topic-ID: ' . $id);
            }

            if (isset($seenIds[$id])) {
                throw new RuntimeException('Doppelte Topic-ID: ' . $id);
            }
            $seenIds[$id] = true;

            $enabled = $item['enabled'] ?? true;
            if (!is_bool($enabled)) {
                throw new RuntimeException(
                    'Der Aktivierungsstatus muss true oder false sein.'
                );
            }

            $normalized[] = [
                'id' => $id,
                'name' => $name,
                'unit' => $unit,
                'enabled' => $enabled
            ];
        }
    }
    $config['topics'] = $normalized;
    return $config;
}


function bayrol_save_config(array $config): void
{
    $file = bayrol_config_file();
    $directory = dirname($file);

    if (!is_dir($directory) || !is_writable($directory)) {
        throw new RuntimeException(
            'Konfigurationsordner existiert nicht oder ist nicht beschreibbar.'
        );
    }

    $json = json_encode(
        $config,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    if ($json === false) {
        throw new RuntimeException('Konfiguration konnte nicht serialisiert werden.');
    }

    $temporary = tempnam($directory, '.bayrol-');

    if ($temporary === false) {
        throw new RuntimeException('Temporäre Datei konnte nicht angelegt werden.');
    }

    try {
        if (file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('Konfiguration konnte nicht geschrieben werden.');
        }

        @chmod($temporary, 0640);

        if (!rename($temporary, $file)) {
            throw new RuntimeException('Konfigurationsdatei konnte nicht ersetzt werden.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}
