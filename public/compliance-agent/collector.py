#!/usr/bin/env python3

import ipaddress
import json
import os
import queue
import re
import socket
import ssl
import struct
import threading
import time
import urllib.request
import uuid
from datetime import datetime, timezone

PANEL_URL = os.environ.get("PANEL_URL", "").rstrip("/")
COLLECTOR_UUID = os.environ.get("COLLECTOR_UUID", "")
COLLECTOR_TOKEN = os.environ.get("COLLECTOR_TOKEN", "")
IPFIX_PORT = int(os.environ.get("IPFIX_PORT", "2055"))
SYSLOG_PORT = int(os.environ.get("SYSLOG_PORT", "5514"))
VERSION = "1.0.0"

EVENTS = queue.Queue(maxsize=50000)
TEMPLATES = {}
TEMPLATE_LOCK = threading.Lock()

DNS_RE = re.compile(
    r"query from\s+([0-9a-fA-F:.]+):\s+#\d+\s+([A-Za-z0-9._-]+)\.",
    re.IGNORECASE,
)

IE = {
    1: "bytes",
    4: "protocol",
    7: "src_port",
    8: "src_ipv4",
    11: "dst_port",
    12: "dst_ipv4",
    27: "src_ipv6",
    28: "dst_ipv6",
    56: "src_mac",
    150: "flow_start_seconds",
    151: "flow_end_seconds",
    152: "flow_start_milliseconds",
    153: "flow_end_milliseconds",
    225: "post_nat_src_ipv4",
    226: "post_nat_dst_ipv4",
    227: "post_napt_src_port",
    228: "post_napt_dst_port",
    230: "nat_event",
}

PROTOCOLS = {1: "icmp", 6: "tcp", 17: "udp", 47: "gre", 50: "esp"}


def push(event):
    try:
        EVENTS.put_nowait(event)
    except queue.Full:
        try:
            EVENTS.get_nowait()
            EVENTS.put_nowait(event)
        except Exception:
            pass


def uint(data):
    return int.from_bytes(data, "big", signed=False) if data else 0


def decode(element_id, data):
    try:
        if element_id in (8, 12, 225, 226) and len(data) == 4:
            return str(ipaddress.IPv4Address(data))
        if element_id in (27, 28) and len(data) == 16:
            return str(ipaddress.IPv6Address(data))
        if element_id == 56 and len(data) == 6:
            return ":".join(f"{b:02X}" for b in data)
        return uint(data)
    except Exception:
        return None


def when(record, export_time):
    try:
        if record.get("flow_end_milliseconds"):
            value = float(record["flow_end_milliseconds"]) / 1000.0
        elif record.get("flow_start_milliseconds"):
            value = float(record["flow_start_milliseconds"]) / 1000.0
        elif record.get("flow_end_seconds"):
            value = float(record["flow_end_seconds"])
        elif record.get("flow_start_seconds"):
            value = float(record["flow_start_seconds"])
        else:
            value = float(export_time)
        return datetime.fromtimestamp(value, timezone.utc).isoformat()
    except Exception:
        return datetime.now(timezone.utc).isoformat()


def parse_templates(source_key, payload):
    offset = 0
    while offset + 4 <= len(payload):
        template_id, count = struct.unpack("!HH", payload[offset:offset + 4])
        offset += 4
        if template_id < 256 or count == 0:
            break

        fields = []
        valid = True
        for _ in range(count):
            if offset + 4 > len(payload):
                valid = False
                break
            element_id, length = struct.unpack("!HH", payload[offset:offset + 4])
            offset += 4
            enterprise = bool(element_id & 0x8000)
            element_id &= 0x7FFF
            if enterprise:
                if offset + 4 > len(payload):
                    valid = False
                    break
                offset += 4
            fields.append((element_id, length, enterprise))

        if not valid:
            break

        with TEMPLATE_LOCK:
            TEMPLATES[(source_key, template_id)] = fields


def emit(record, export_time):
    src = record.get("src_ipv4") or record.get("src_ipv6")
    dst = record.get("dst_ipv4") or record.get("dst_ipv6")
    proto_num = int(record.get("protocol") or 0)
    proto = PROTOCOLS.get(proto_num, str(proto_num))
    observed = when(record, export_time)

    public_ip = record.get("post_nat_src_ipv4")
    public_port = int(record.get("post_napt_src_port") or 0) or None
    src_port = int(record.get("src_port") or 0) or None
    dst_port = int(record.get("dst_port") or 0) or None
    mac = record.get("src_mac")

    if src and public_ip and public_port and public_ip != "0.0.0.0":
        push({
            "type": "nat",
            "protocol": proto,
            "public_ip": public_ip,
            "public_port": public_port,
            "private_ip": src,
            "private_port": src_port,
            "destination_ip": dst,
            "destination_port": dst_port,
            "mac_address": mac,
            "started_at": observed,
            "ended_at": observed,
        })

    if src or dst:
        push({
            "type": "browse",
            "event_type": "flow",
            "observed_at": observed,
            "source_ip": src,
            "source_port": src_port,
            "source_mac": mac,
            "destination_ip": dst,
            "destination_port": dst_port,
            "public_ip": public_ip,
            "public_port": public_port,
            "protocol": proto,
            "bytes_up": int(record.get("bytes") or 0),
            "bytes_down": 0,
            "blocked": False,
        })


def parse_data(source_key, set_id, payload, export_time):
    with TEMPLATE_LOCK:
        fields = TEMPLATES.get((source_key, set_id))
    if not fields:
        return

    offset = 0
    while offset < len(payload):
        start = offset
        record = {}
        ok = True

        for element_id, length, enterprise in fields:
            if length == 65535:
                if offset >= len(payload):
                    ok = False
                    break
                first = payload[offset]
                offset += 1
                if first < 255:
                    length = first
                else:
                    if offset + 2 > len(payload):
                        ok = False
                        break
                    length = struct.unpack("!H", payload[offset:offset + 2])[0]
                    offset += 2

            if offset + length > len(payload):
                ok = False
                break

            raw = payload[offset:offset + length]
            offset += length

            if enterprise:
                continue

            name = IE.get(element_id)
            if name:
                record[name] = decode(element_id, raw)

        if not ok or offset <= start:
            break

        emit(record, export_time)

        if len(payload) - offset < 4:
            break


def parse_ipfix(data, source):
    if len(data) < 16:
        return

    version, length, export_time, sequence, domain_id = struct.unpack(
        "!HHIII", data[:16]
    )

    if version != 10 or length > len(data):
        return

    source_key = (source[0], domain_id)
    offset = 16

    while offset + 4 <= length:
        set_id, set_length = struct.unpack("!HH", data[offset:offset + 4])
        if set_length < 4 or offset + set_length > length:
            break

        payload = data[offset + 4:offset + set_length]
        if set_id == 2:
            parse_templates(source_key, payload)
        elif set_id >= 256:
            parse_data(source_key, set_id, payload, export_time)

        offset += set_length


def ipfix_loop():
    sock = socket.socket(socket.AF_INET6, socket.SOCK_DGRAM)
    sock.setsockopt(socket.IPPROTO_IPV6, socket.IPV6_V6ONLY, 0)
    sock.bind(("::", IPFIX_PORT))

    while True:
        data, source = sock.recvfrom(65535)
        try:
            parse_ipfix(data, source)
        except Exception:
            pass


def syslog_loop():
    sock = socket.socket(socket.AF_INET6, socket.SOCK_DGRAM)
    sock.setsockopt(socket.IPPROTO_IPV6, socket.IPV6_V6ONLY, 0)
    sock.bind(("::", SYSLOG_PORT))

    while True:
        data, source = sock.recvfrom(65535)
        try:
            text = data.decode("utf-8", errors="replace")
            match = DNS_RE.search(text)
            if not match:
                continue
            push({
                "type": "browse",
                "event_type": "dns",
                "observed_at": datetime.now(timezone.utc).isoformat(),
                "source_ip": match.group(1),
                "domain": match.group(2).lower(),
                "protocol": "dns",
                "bytes_up": 0,
                "bytes_down": 0,
                "blocked": False,
            })
        except Exception:
            pass


def api(path, payload):
    body = json.dumps(payload, separators=(",", ":")).encode("utf-8")
    request = urllib.request.Request(
        PANEL_URL + path,
        data=body,
        method="POST",
        headers={
            "Authorization": "Bearer " + COLLECTOR_TOKEN,
            "X-Collector-UUID": COLLECTOR_UUID,
            "Content-Type": "application/json",
            "Accept": "application/json",
            "User-Agent": "MikroPanel-Compliance-Collector/" + VERSION,
        },
    )

    with urllib.request.urlopen(
        request,
        timeout=20,
        context=ssl.create_default_context(),
    ) as response:
        raw = response.read()
        return json.loads(raw.decode("utf-8")) if raw else {}


def heartbeat_loop():
    while True:
        try:
            api("/api/compliance/v1/heartbeat", {
                "version": VERSION,
                "capabilities": {
                    "ipfix": True,
                    "nat_ipfix": True,
                    "dns_syslog": True,
                    "payload_capture": False,
                },
            })
        except Exception:
            pass
        time.sleep(30)


def flush_loop():
    pending = []

    while True:
        try:
            pending.append(EVENTS.get(timeout=2))
        except queue.Empty:
            pass

        if not pending:
            continue

        if len(pending) < 250 and EVENTS.qsize() > 0:
            continue

        batch = pending[:500]

        try:
            api("/api/compliance/v1/batch", {
                "batch_uuid": str(uuid.uuid4()),
                "events": batch,
            })
            pending = pending[len(batch):]
        except Exception:
            time.sleep(5)
            if len(pending) > 5000:
                pending = pending[-5000:]


def main():
    if not PANEL_URL or not COLLECTOR_UUID or not COLLECTOR_TOKEN:
        raise SystemExit("Collector environment is incomplete.")

    for target in (ipfix_loop, syslog_loop, heartbeat_loop, flush_loop):
        threading.Thread(target=target, daemon=True).start()

    while True:
        time.sleep(3600)


if __name__ == "__main__":
    main()
