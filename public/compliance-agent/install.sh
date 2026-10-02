#!/usr/bin/env bash
set -Eeuo pipefail

PANEL_URL="${1:-}"
COLLECTOR_UUID="${2:-}"
COLLECTOR_TOKEN="${3:-}"
IPFIX_PORT="${4:-2055}"

if [ -z "$PANEL_URL" ] || [ -z "$COLLECTOR_UUID" ] || [ -z "$COLLECTOR_TOKEN" ]; then
    echo "Usage: install.sh PANEL_URL COLLECTOR_UUID COLLECTOR_TOKEN [IPFIX_PORT]"
    exit 1
fi

if [ "$(id -u)" -ne 0 ]; then
    echo "Run as root."
    exit 1
fi

case "$PANEL_URL" in
    https://*) ;;
    *)
        echo "PANEL_URL must use HTTPS."
        exit 1
        ;;
esac

install -d -m 0750 /opt/mikropanel-compliance

curl -fsSL \
    "${PANEL_URL%/}/compliance-agent/collector.py" \
    -o /opt/mikropanel-compliance/collector.py

chmod 0755 /opt/mikropanel-compliance/collector.py
python3 -m py_compile /opt/mikropanel-compliance/collector.py

cat > /etc/mikropanel-compliance-collector.env <<EOF2
PANEL_URL=${PANEL_URL%/}
COLLECTOR_UUID=${COLLECTOR_UUID}
COLLECTOR_TOKEN=${COLLECTOR_TOKEN}
IPFIX_PORT=${IPFIX_PORT}
SYSLOG_PORT=5514
EOF2

chmod 0600 /etc/mikropanel-compliance-collector.env

cat > /etc/systemd/system/mikropanel-compliance-collector.service <<'EOF2'
[Unit]
Description=MikroPanel Compliance Collector
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
EnvironmentFile=/etc/mikropanel-compliance-collector.env
ExecStart=/usr/bin/python3 /opt/mikropanel-compliance/collector.py
Restart=always
RestartSec=5
NoNewPrivileges=true
PrivateTmp=true
ProtectHome=true
MemoryMax=256M

[Install]
WantedBy=multi-user.target
EOF2

systemctl daemon-reload
systemctl enable --now mikropanel-compliance-collector.service
sleep 2
systemctl is-active mikropanel-compliance-collector.service

echo "COLLECTOR_INSTALL=PASS"
echo "IPFIX_UDP=${IPFIX_PORT}"
echo "DNS_SYSLOG_UDP=5514"
