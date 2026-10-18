#!/bin/bash
set -e

PLUGIN="REPLACELBPPLUGINDIR"
PCONFIG="REPLACELBPCONFIGDIR"
LBHOME="REPLACELBHOMEDIR"

SOURCE="$PCONFIG/config.json"
BACKUP="$LBHOME/data/system/${PLUGIN}-config-backup.json"

if [ -f "$SOURCE" ]; then
    cp -p "$SOURCE" "$BACKUP"
    chmod 600 "$BACKUP"
    echo "<INFO> Bayrol-Konfiguration vor Upgrade gesichert"
else
    echo "<INFO> Keine bestehende Bayrol-Konfiguration gefunden"
fi

exit 0
