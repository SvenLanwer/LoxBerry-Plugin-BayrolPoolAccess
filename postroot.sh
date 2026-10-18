#!/bin/bash

PLUGIN="bayrolpoolaccess"
LBHOME="${5:-/opt/loxberry}"
TEMPDIR="${6:-$(pwd)}"

SERVICE="$PLUGIN.service"
SOURCE="$TEMPDIR/systemd/$SERVICE"
TARGET="/etc/systemd/system/$SERVICE"

echo "<INFO> Installiere Bayrol PoolAccess Systemdienst"

if [ ! -f "$SOURCE" ]; then
    echo "<ERROR> Service-Datei fehlt: $SOURCE"
    exit 2
fi

install -m 644 "$SOURCE" "$TARGET" || exit 2

# Systemd-Konfiguration aktualisieren
systemctl daemon-reload || exit 2

# Autostart dauerhaft aktivieren
systemctl enable "$SERVICE" || {
    echo "<ERROR> Autostart konnte nicht aktiviert werden"
    exit 2
}

# Dienst starten oder bei einem Upgrade neu starten
systemctl restart "$SERVICE" || {
    echo "<ERROR> Bayrol-Dienst konnte nicht gestartet werden"
    exit 2
}

echo "<OK> Bayrol PoolAccess Systemdienst installiert"
echo "<OK> Automatischer Start beim LoxBerry-Neustart aktiviert"

if systemctl is-active --quiet "$SERVICE"; then
    echo "<OK> Bayrol PoolAccess Dienst läuft"
else
    echo "<INFO> Dienst wartet auf die Konfiguration"
    echo "<INFO> Bitte Bayrol-Zugangsdaten und MQTT-Einstellungen in der Weboberfläche eintragen"
fi

exit 0


