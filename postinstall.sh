#!/bin/bash
set -e

PBIN="REPLACELBPBINDIR"
PCONFIG="REPLACELBPCONFIGDIR"
PDATA="REPLACELBPDATADIR"
VENV="$PDATA/venv"

echo "<INFO> Richte Bayrol PoolAccess ein"

mkdir -p "$PCONFIG" "$PDATA"

# Gesicherte Konfiguration nach einem Upgrade wiederherstellen
PLUGIN="REPLACELBPPLUGINDIR"
LBHOME="REPLACELBHOMEDIR"
BACKUP="$LBHOME/data/system/${PLUGIN}-config-backup.json"

if [ -f "$BACKUP" ] && [ ! -f "$PCONFIG/config.json" ]; then
    cp -p "$BACKUP" "$PCONFIG/config.json"
    chmod 600 "$PCONFIG/config.json"
    echo "<OK> Bayrol-Konfiguration nach Upgrade wiederhergestellt"
fi


# Vorhandene Konfiguration niemals überschreiben
if [ ! -f "$PCONFIG/config.json" ]; then
    if [ ! -f "$PCONFIG/config.example.json" ]; then
        echo "<ERROR> config.example.json fehlt"
        exit 2
    fi

    cp "$PCONFIG/config.example.json" "$PCONFIG/config.json"
    chmod 600 "$PCONFIG/config.json"
    echo "<INFO> Neue Konfiguration angelegt"
fi

# Python-Abhängigkeiten installieren
if [ ! -f "$PBIN/requirements.txt" ]; then
    echo "<ERROR> requirements.txt fehlt"
    exit 2
fi

if [ ! -x "$VENV/bin/python" ]; then
    python3 -m venv "$VENV" || {
        echo "<ERROR> Python-Umgebung konnte nicht erstellt werden"
        exit 2
    }
fi

"$VENV/bin/python" -m pip install -r "$PBIN/requirements.txt" || {
    echo "<ERROR> Python-Abhängigkeiten konnten nicht installiert werden"
    exit 2
}

echo "<OK> Bayrol PoolAccess erfolgreich vorbereitet"
exit 0
