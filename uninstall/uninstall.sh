#!/bin/bash
set -e

SERVICE="bayrolpoolaccess.service"
SERVICE_FILE="/etc/systemd/system/$SERVICE"

echo "<INFO> Entferne Bayrol PoolAccess Systemdienst"

systemctl disable --now "$SERVICE" 2>/dev/null || true

rm -f "$SERVICE_FILE"

systemctl daemon-reload

echo "<OK> Bayrol PoolAccess Systemdienst entfernt"

exit 0
