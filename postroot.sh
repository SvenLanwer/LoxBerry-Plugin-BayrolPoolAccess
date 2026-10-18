#!/bin/bash
set -e

PLUGIN="REPLACELBPPLUGINDIR"
TEMPDIR="${6:-$(pwd)}"

SERVICE="${PLUGIN}.service"
SOURCE="$TEMPDIR/systemd/bayrolpoolaccess.service"
TARGET="/etc/systemd/system/$SERVICE"

echo "<INFO> Installiere Bayrol PoolAccess Systemdienst"

if [ ! -f "$SOURCE" ]; then
    echo "<ERROR> Service-Datei fehlt: $SOURCE"
    exit 2
fi

install -m 644 "$SOURCE" "$TARGET"

systemctl daemon-reload
systemctl enable "$SERVICE"

# Nur starten, wenn die Konfiguration vollständig ist
if systemctl restart "$SERVICE"; then
    if systemctl is-active --quiet "$SERVICE"; then
        echo "<OK> Bayrol PoolAccess Dienst läuft"
    else
        echo "<INFO> Dienst wartet auf die Konfiguration"
    fi
else
    echo "<INFO> Bayrol-Dienst konnte noch nicht gestartet werden"
    echo "<INFO> Bitte Konfiguration in der Weboberfläche prüfen"
fi

echo "<OK> Bayrol PoolAccess Systemdienst installiert"
exit 0
