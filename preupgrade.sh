#!/bin/bash
set -e

PLUGIN="bayrolpoolaccess"
LBHOME="${5:-/opt/loxberry}"

SOURCE="$LBHOME/config/plugins/$PLUGIN/config.json"
BACKUP="$LBHOME/data/system/install/${PLUGIN}-config-backup.json"

if [ -f "$SOURCE" ]; then
    mkdir -p "$(dirname "$BACKUP")"
    cp -p "$SOURCE" "$BACKUP"
    chmod 600 "$BACKUP"
    echo "<INFO> Bayrol-Konfiguration vor Upgrade gesichert"
else
    echo "<INFO> Keine bestehende Bayrol-Konfiguration gefunden"
fi

exit 0