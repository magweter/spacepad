#!/usr/bin/env python3
"""Fake Philips SICP server for testing the Spacepad LED test page without hardware.

Frame: [MsgSize][MonitorID][Group][Data...][Checksum], MsgSize counts every byte,
Checksum is the XOR of all preceding bytes.

  0xF3 (Set LED, data: on/off R G B) -> ACK, remembers the colour
  0xF4 (Get LED)                     -> F4 on/off R G B with the last colour set
  bad length or checksum             -> NACK
  any other command                  -> NAV

Usage:
  python3 tools/fake_sicp_server.py [--host 127.0.0.1] [--port 5000] [--mode ack|nack|nav|silent]

On an Android emulator, run `adb reverse tcp:5000 tcp:5000` so 127.0.0.1:5000 in the app
reaches this server.
"""

import argparse
import socketserver
import sys
import threading

ACK, NACK, NAV = 0x06, 0x15, 0x18
SET_LED, GET_LED = 0xF3, 0xF4

state = {"on": 0, "rgb": (0, 0, 0)}
state_lock = threading.Lock()
mode = "ack"


def hexs(data):
    return " ".join(f"{b:02X}" for b in data)


def frame(monitor_id, data):
    out = [len(data) + 4, monitor_id, 0x00, *data]
    checksum = 0
    for b in out:
        checksum ^= b
    return bytes(out + [checksum])


def report(monitor_id, code):
    return frame(monitor_id, [0x00, code])


def handle(msg):
    monitor_id = msg[1] if len(msg) > 1 else 0x01

    checksum = 0
    for b in msg[:-1]:
        checksum ^= b
    if len(msg) < 5 or msg[0] != len(msg) or checksum != msg[-1]:
        return report(monitor_id, NACK)

    if mode == "nack":
        return report(monitor_id, NACK)
    if mode == "nav":
        return report(monitor_id, NAV)

    data = msg[3:-1]
    command = data[0]

    if command == SET_LED and len(data) == 5:
        with state_lock:
            state["on"] = 1 if data[1] else 0
            state["rgb"] = (data[2], data[3], data[4])
        print(f"  LED {'on ' if data[1] else 'off'} #{data[2]:02X}{data[3]:02X}{data[4]:02X}")
        return report(monitor_id, ACK)

    if command == GET_LED and len(data) == 1:
        with state_lock:
            return frame(monitor_id, [GET_LED, state["on"], *state["rgb"]])

    return report(monitor_id, NAV)


class Handler(socketserver.BaseRequestHandler):
    def handle(self):
        buffer = b""
        while True:
            chunk = self.request.recv(256)
            if not chunk:
                return
            buffer += chunk
            # MsgSize is the first byte; wait until the whole frame is in.
            while buffer and len(buffer) >= max(buffer[0], 1):
                size = max(buffer[0], 1)
                msg, buffer = buffer[:size], buffer[size:]
                print(f"RX {hexs(msg)}")
                if mode == "silent":
                    print("  (silent, no reply)")
                    continue
                reply = handle(msg)
                print(f"TX {hexs(reply)}")
                self.request.sendall(reply)


class Server(socketserver.ThreadingTCPServer):
    allow_reuse_address = True
    daemon_threads = True


def main():
    global mode
    parser = argparse.ArgumentParser(description="Fake Philips SICP server")
    parser.add_argument("--host", default="127.0.0.1")
    parser.add_argument("--port", type=int, default=5000)
    parser.add_argument("--mode", choices=["ack", "nack", "nav", "silent"], default="ack",
                        help="ack: normal behaviour; nack/nav: answer every valid frame with it; silent: never answer")
    args = parser.parse_args()
    mode = args.mode
    sys.stdout.reconfigure(line_buffering=True)

    with Server((args.host, args.port), Handler) as server:
        print(f"Fake SICP server on {args.host}:{args.port} (mode: {mode})")
        try:
            server.serve_forever()
        except KeyboardInterrupt:
            pass


if __name__ == "__main__":
    main()
