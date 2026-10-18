#!/bin/bash
set -e

PLUGIN="REPLACELBPPLUGINDIR"
SERVICE="${PLUGIN}.service"
SERVICE_FILE="/etc/systemd/system/$SERVICE"

echo "<INFO> Entferne Bayrol PoolAccess Systemdienst: $SERVICE"

systemctl disable --now "$SERVICE" 2>/dev/null || true

rm -f "$SERVICE_FILE"

systemctl daemon-reload

echo "<OK> Bayrol PoolAccess Systemdienst entfernt"

exit 0
