<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once 'loxberry_system.php';
require_once 'loxberry_web.php';
require_once __DIR__ . '/config.php';

if (empty($_SESSION['bayrol_csrf'])) {
    $_SESSION['bayrol_csrf'] = bin2hex(random_bytes(32));
}
function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function field(array $values, string $key): string {
    return h($values[$key] ?? '');
}
function port_from_post(array $data, string $label): int {
    $port = filter_var($data['port'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
    if ($port === false) {
        throw new RuntimeException('Ungültiger Port für ' . $label . '.');
    }
    return $port;
}

$message = '';
$error = '';
try {
    $config = bayrol_normalize_config(bayrol_read_config());
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        if (!hash_equals((string)$_SESSION['bayrol_csrf'], (string)($_POST['csrf'] ?? ''))) {
            throw new RuntimeException('Sicherheitsprüfung fehlgeschlagen. Bitte Seite neu laden.');
        }
        if (($_POST['action'] ?? '') === 'save') {
            $source = $_POST['source'] ?? null;
            $destination = $_POST['destination'] ?? null;
            $submitted = $_POST['topics'] ?? null;
            if (!is_array($source) || !is_array($destination) || !is_array($submitted)) {
                throw new RuntimeException('Ungültige Formulardaten.');
            }
            foreach (['source' => $source, 'destination' => $destination] as $section => $data) {
                foreach (['host', 'username', 'topic_prefix'] as $key) {
                    if (!isset($data[$key]) || !is_string($data[$key])) {
                        throw new RuntimeException('Ungültiges Feld: ' . $section . '.' . $key);
                    }
                    $config[$section][$key] = trim($data[$key]);
                }
                if (!isset($data['password']) || !is_string($data['password'])) {
                    throw new RuntimeException('Ungültiges Passwortfeld.');
                }
                if ($data['password'] !== '') {
                    $config[$section]['password'] = $data['password'];
                }
                $config[$section]['port'] = port_from_post($data, $section);
            }
            if ($config['source']['host'] === '' || $config['source']['topic_prefix'] === '' || $config['destination']['host'] === '') {
                throw new RuntimeException('Bayrol-Host, Quell-Topic-Präfix und MQTT-Zielhost dürfen nicht leer sein.');
            }
            $updated = [];
            $seen = [];
            foreach ($submitted as $item) {
                if (!is_array($item)) {
                    throw new RuntimeException('Ungültiger Messwert-Eintrag.');
                }
                $id = trim((string)($item['id'] ?? ''));
                $name = trim((string)($item['name'] ?? ''));
                $unit = trim((string)($item['unit'] ?? ''));
                if (!preg_match('/^[A-Za-z0-9._-]+$/', $id)) {
                    throw new RuntimeException('Ungültige Topic-ID: ' . $id);
                }
                if (isset($seen[$id])) {
                    throw new RuntimeException('Topic-ID doppelt vorhanden: ' . $id);
                }
                $seen[$id] = true;
                $updated[] = ['id' => $id, 'name' => $name !== '' ? $name : 'Messwert ' . $id, 'unit' => $unit, 'enabled' => isset($item['enabled']) && $item['enabled'] === '1'];
            }
            $newId = trim((string)($_POST['new_id'] ?? ''));
            if ($newId !== '') {
                if (!preg_match('/^[A-Za-z0-9._-]+$/', $newId) || isset($seen[$newId])) {
                    throw new RuntimeException('Neue Topic-ID ist ungültig oder bereits vorhanden.');
                }
                $updated[] = ['id' => $newId, 'name' => trim((string)($_POST['new_name'] ?? '')) ?: 'Messwert ' . $newId, 'unit' => trim((string)($_POST['new_unit'] ?? '')), 'enabled' => true];
            }
            if (count($updated) > 200) {
                throw new RuntimeException('Maximal 200 Topics sind erlaubt.');
            }
            $config['topics'] = $updated;
            bayrol_save_config($config);

            // Bayrol-Dienst nach dem Speichern neu starten
            $command = '/usr/bin/sudo -n /usr/bin/systemctl restart bayrolpoolaccess.service 2>&1';
            $output = [];
            $returnCode = 0;

            exec($command, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new RuntimeException(
                    'Konfiguration gespeichert, aber der Bayrol-Dienst konnte nicht neu gestartet werden. '
                    . 'Bitte den Dienststatus prüfen.'
                );
            }

            header('Location: index.php?saved=1');
            exit;
        }
    }
    if (isset($_GET['saved'])) {
        $message = 'Konfiguration gespeichert.';
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$template_title = 'Bayrol PoolAccess';
$helplink = 'https://wiki.loxberry.de/';
$helptemplate = 'pluginhelp.html';
$navbar[1]['Name'] = 'Konfiguration';
$navbar[1]['URL'] = 'index.php';
$navbar[1]['active'] = true;
LBWeb::lbheader($template_title, $helplink, $helptemplate);
?>
<link rel="stylesheet" href="style.css">
<main class="container">
    <header class="page-header">
        <div>
            <p class="eyebrow">LOXBERRY · MQTT BRIDGE</p>
            <h1>Bayrol PoolAccess</h1>
            <p class="subtitle">Verbindungseinstellungen und Messwerte verwalten</p>
        </div>
        <span class="status status-neutral">Konfiguration</span>
    </header>
    <?php if ($message !== ''): ?><div class="notice success"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>
    <?php if (isset($config) && is_array($config)): ?>
    <form method="post" action="index.php" autocomplete="off">
        <input type="hidden" name="csrf" value="<?= h($_SESSION['bayrol_csrf']) ?>">
        <input type="hidden" name="action" value="save">

        <section class="panel">
            <div class="section-heading"><div><h2>Bayrol-Verbindung</h2><p>Zugang zur PoolAccess-Datenquelle.</p></div></div>
            <div class="add-grid">
                <label>Server<input type="text" name="source[host]" value="<?= field($config['source'], 'host') ?>" required></label>
                <label>Port<input type="number" name="source[port]" value="<?= field($config['source'], 'port') ?>" min="1" max="65535" required></label>
                <label>Benutzername<input type="text" name="source[username]" value="<?= field($config['source'], 'username') ?>" autocomplete="username"></label>
                <label>Passwort<input type="password" name="source[password]" value="" class="<?= !empty($config['source']['password']) ? 'password-saved' : '' ?>" placeholder="<?= !empty($config['source']['password']) ? '********' : 'Passwort eingeben' ?>" autocomplete="new-password"></label>
                <label>Quell-Topic-Präfix<input type="text" name="source[topic_prefix]" value="<?= field($config['source'], 'topic_prefix') ?>" required></label>
            </div>
        </section>

        <section class="panel">
            <div class="section-heading"><div><h2>MQTT-Ziel</h2><p>Lokaler Broker, an den die Messwerte gesendet werden.</p></div></div>
            <div class="add-grid">
                <label>Broker-IP / Host<input type="text" name="destination[host]" value="<?= field($config['destination'], 'host') ?>" required></label>
                <label>Port<input type="number" name="destination[port]" value="<?= field($config['destination'], 'port') ?>" min="1" max="65535" required></label>
                <label>Benutzername (optional)<input type="text" name="destination[username]" value="<?= field($config['destination'], 'username') ?>" autocomplete="off"></label>
                <label>Passwort <input type="password" name="destination[password]" value="" class="<?= !empty($config['destination']['password']) ? 'password-saved' : '' ?>" placeholder="<?= !empty($config['destination']['password']) ? '********' : 'Passwort eingeben' ?>" autocomplete="new-password"></label>
                <label>Ziel-Topic-Präfix<input type="text" name="destination[topic_prefix]" value="<?= field($config['destination'], 'topic_prefix') ?>"></label>
            </div>
        </section>

        <section class="panel">
            <div class="section-heading"><div><h2>Messwerte</h2><p>Aktiviere die Werte, die abonniert werden sollen.</p></div><span class="count"><?= count($config['topics']) ?> Topics</span></div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Aktiv</th><th>Topic-ID</th><th>Name</th><th>Einheit</th></tr></thead>
                    <tbody>
                    <?php foreach ($config['topics'] as $i => $topic): ?>
                        <tr>
                            <td><label class="switch-label"><input type="checkbox" name="topics[<?= $i ?>][enabled]" value="1" <?= $topic['enabled'] ? 'checked' : '' ?>><span class="switch-ui"></span><span class="sr-only">Messwert aktivieren</span></label></td>
                            <td><input type="text" name="topics[<?= $i ?>][id]" value="<?= h($topic['id']) ?>" pattern="[A-Za-z0-9._-]+" required></td>
                            <td><input type="text" name="topics[<?= $i ?>][name]" value="<?= h($topic['name']) ?>" maxlength="100"></td>
                            <td><input type="text" name="topics[<?= $i ?>][unit]" value="<?= h($topic['unit']) ?>" maxlength="20"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <section class="add-topic">
                <h3>Neuen Messwert hinzufügen</h3>
                <p>Topic-ID beispielsweise <code>4.123</code>.</p>
                <div class="add-grid">
                    <label>Topic-ID<input type="text" name="new_id" pattern="[A-Za-z0-9._-]+" placeholder="z. B. 4.123"></label>
                    <label>Anzeigename<input type="text" name="new_name" maxlength="100" placeholder="Name des Messwerts"></label>
                    <label>Einheit<input type="text" name="new_unit" maxlength="20" placeholder="z. B. °C"></label>
                </div>
            </section>
            <div class="form-footer"><p>Alle Einstellungen werden gemeinsam gespeichert.</p><button type="submit">Änderungen speichern</button></div>
        </section>
    </form>
    <section class="panel info-panel"><h2>Verbindungsstatus</h2><p>Der Live-Verbindungsstatus wird in einem späteren Schritt ergänzt.</p><p class="muted">Ein hinzugefügtes Topic ist nur verfügbar, wenn Bayrol Daten für diese ID liefert.</p></section>
    <?php endif; ?>
    <footer>Bayrol PoolAccess · Lokale Plugin-Konfiguration</footer>
</main>
<?php LBWeb::lbfooter(); ?>