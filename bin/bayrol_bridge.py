
#!/usr/bin/env python3
"""Bayrol PoolAccess -> MQTT Bridge."""

import argparse
import json
import logging
import sys
import time
import threading
from pathlib import Path

import paho.mqtt.client as mqtt


DEFAULT_CONFIG = (
    Path(__file__).resolve().parents[1] / "config" / "config.json"
)



def load_config(path: Path) -> dict:
    with path.open("r", encoding="utf-8") as file:
        config = json.load(file)

    for section in ("source", "destination"):
        if section not in config:
            raise ValueError(f"Konfigurationsabschnitt fehlt: {section}")

    source = config["source"]
    destination = config["destination"]

    for section, fields in (
        (source, ("host", "port", "username", "password")),
        (destination, ("host", "port", "topic_prefix")),
    ):
        for field in fields:
            if field not in section:
                raise ValueError(f"Konfigurationsfeld fehlt: {field}")

    if not source["username"] or not source["password"]:
        raise ValueError("Bayrol-Zugangsdaten fehlen in config.json.")

    if not destination["host"]:
        raise ValueError("MQTT-Broker-Adresse fehlt in config.json.")

    topics = config.get("topics", [])
    if not isinstance(topics, list) or not topics:
        raise ValueError("Es sind keine Bayrol-Topics konfiguriert.")

    for topic in topics:
        if not isinstance(topic, dict):
            raise ValueError("Jeder Topic-Eintrag muss ein Objekt sein.")

        for field in ("id", "name", "unit", "enabled"):
            if field not in topic:
                raise ValueError(
                    f"Topic-Eintrag enthält das Pflichtfeld '{field}' nicht."
                )

        if not isinstance(topic["id"], str) or not topic["id"].strip():
            raise ValueError("Topic-ID darf nicht leer sein.")

        if not isinstance(topic["enabled"], bool):
            raise ValueError(
                f"Das Feld 'enabled' muss für Topic {topic['id']} true oder false sein."
            )

    return config


def build_topic_data(config):
    """Erstellt die aktive Topic-Liste und ihre Metadaten."""
    source_cfg = config["source"]
    raw_topics = config["topics"]

    if not isinstance(raw_topics, list):
        raise ValueError("Die Topic-Liste ist ungültig.")

    topics = []
    topic_metadata = {}

    # Leere Liste ist erlaubt: Alle Messwerte können deaktiviert sein.
    if not raw_topics:
        return topics, topic_metadata

    # Rückwärtskompatibilität: vollständige Topic-Strings
    if all(isinstance(item, str) for item in raw_topics):
        for full_topic in raw_topics:
            if not full_topic.strip():
                raise ValueError("Ein Topic darf nicht leer sein.")

            topics.append(full_topic)
            topic_metadata[full_topic] = {
                "id": full_topic.rsplit("/", 1)[-1],
                "name": full_topic,
                "unit": "",
                "enabled": True,
            }

        return topics, topic_metadata

    # Neues Format: ID, Name, Einheit und Aktivierung
    prefix = source_cfg.get("topic_prefix", "").rstrip("/")

    if not prefix:
        raise ValueError(
            "Bei dynamischen Topics fehlt source.topic_prefix."
        )

    for item in raw_topics:
        if not isinstance(item, dict):
            raise ValueError(
                "Jeder Topic-Eintrag muss ein Objekt sein."
            )

        topic_id = str(item.get("id", "")).strip()

        if not topic_id or "/" in topic_id:
            raise ValueError(f"Ungültige Topic-ID: {topic_id!r}")

        if not item.get("enabled", True):
            continue

        full_topic = f"{prefix}/{topic_id}"

        if full_topic in topic_metadata:
            raise ValueError(f"Doppelte Topic-ID: {topic_id}")

        topics.append(full_topic)
        topic_metadata[full_topic] = {
            "id": topic_id,
            "name": str(item.get("name", topic_id)),
            "unit": str(item.get("unit", "")),
            "enabled": True,
        }

    return topics, topic_metadata


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--config",
        type=Path,
        default=DEFAULT_CONFIG,
        help="Pfad zur Konfigurationsdatei",
    )
    args = parser.parse_args()

    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(message)s",
    )

    try:
        config = load_config(args.config)
    except (OSError, json.JSONDecodeError, ValueError) as exc:
        logging.error("Konfiguration konnte nicht geladen werden: %s", exc)
        return 2

    source_cfg = config["source"]
    destination_cfg = config["destination"]

    # MQTT-Zielpräfix normalisieren
    topic_prefix = destination_cfg["topic_prefix"].strip("/")

    # Unterstützt alte Topic-Listen und neue dynamische Einträge

    topics, topic_metadata = build_topic_data(config)

    logging.info("Aktive Bayrol-Topics: %d", len(topics))
    for full_topic in topics:
        logging.info(
            "Topic aktiviert: %s (%s)",
            topic_metadata[full_topic]["id"],
            topic_metadata[full_topic]["name"],
        )


    logging.info("Aktive Bayrol-Topics: %d", len(topics))
    for full_topic in topics:
        logging.info(
            "Topic aktiviert: %s (%s)",
            topic_metadata[full_topic]["id"],
            topic_metadata[full_topic]["name"],
        )

    destination = mqtt.Client(
        callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
        client_id="bayrol-poolaccess-bridge",
        protocol=mqtt.MQTTv311,
    )

    if destination_cfg.get("username"):
        destination.username_pw_set(
            destination_cfg["username"],
            destination_cfg.get("password", ""),
        )

    def on_destination_connect(client, userdata, flags, reason_code, properties):
        if reason_code.is_failure:
            logging.error("MQTT-Broker-Verbindung fehlgeschlagen: %s", reason_code)
        else:
            logging.info(
                "Mit lokalem MQTT-Broker verbunden: %s:%s",
                destination_cfg["host"],
                destination_cfg["port"],
            )

    destination.on_connect = on_destination_connect
    destination.reconnect_delay_set(min_delay=1, max_delay=60)

    source = mqtt.Client(
        callback_api_version=mqtt.CallbackAPIVersion.VERSION2,
        client_id="bayrol-poolaccess-source",
        transport="websockets",
        protocol=mqtt.MQTTv311,
    )
    source.username_pw_set(source_cfg["username"], source_cfg["password"])
    source.tls_set()
    source.reconnect_delay_set(min_delay=1, max_delay=60)

    def on_source_connect(client, userdata, flags, reason_code, properties):
        if reason_code.is_failure:
            logging.error("Bayrol-Verbindung fehlgeschlagen: %s", reason_code)
            return

        logging.info("Mit Bayrol PoolAccess verbunden.")



        if not topics:
            logging.warning("Keine Bayrol-Topics aktiviert.")
            return

        result, message_ids = client.subscribe(
            [(topic, 0) for topic in topics]
        )

        if result != mqtt.MQTT_ERR_SUCCESS:
            logging.error(
                "Bayrol-Topics konnten nicht abonniert werden: %s",
                result,
            )
        else:
            logging.info(
                "Abonnements angefordert: %d Topics",
                len(topics),
            )


    def on_source_subscribe(client, userdata, mid, reason_code_list, properties):
        for reason in reason_code_list:
            if reason.is_failure:
                logging.error("Bayrol-Topic-Abonnement abgelehnt: %s", reason)
        logging.info("Bayrol-Abonnementbestätigungen empfangen.")

    def on_source_message(client, userdata, message):
        output_topic = f"{topic_prefix}/{message.topic.lstrip('/')}"
        info = destination.publish(
            output_topic,
            payload=message.payload,
            qos=message.qos,
            retain=False,
        )

        if info.rc == mqtt.MQTT_ERR_SUCCESS:
            logging.info(
                "Messwert weitergeleitet: %s -> %s",
                message.topic,
                output_topic,
            )
        else:
            logging.error(
                "MQTT-Veröffentlichung fehlgeschlagen (%s): %s",
                info.rc,
                output_topic,
            )

    def reload_config_loop():
        nonlocal topics, topic_metadata

        while not stop_event.wait(10):
            try:
                new_config = load_config(args.config)
                new_topics, new_metadata = build_topic_data(new_config)

                # Die bestehende Verbindungskonfiguration bleibt
                # während des Betriebs unverändert.
                if (
                    new_config["source"] != source_cfg
                    or new_config["destination"] != destination_cfg
                ):
                    logging.warning(
                        "Verbindungseinstellungen geändert. "
                        "Diese Änderungen benötigen einen Neustart."
                    )
                    continue

                old_set = set(topics)
                new_set = set(new_topics)

                added = sorted(new_set - old_set)
                removed = sorted(old_set - new_set)

                if not added and not removed:
                    topic_metadata = new_metadata
                    continue

                # Neue Topics zuerst abonnieren.
                if added:
                    result, _ = source.subscribe(
                        [(topic, 0) for topic in added]
                    )
                    if result != mqtt.MQTT_ERR_SUCCESS:
                        logging.error(
                            "Neue Topics konnten nicht abonniert werden: %s",
                            result,
                        )
                        continue

                if removed:
                    result, _ = source.unsubscribe(removed)
                    if result != mqtt.MQTT_ERR_SUCCESS:
                        logging.error(
                            "Topics konnten nicht abbestellt werden: %s",
                            result,
                        )
                        continue

                topics = new_topics
                topic_metadata = new_metadata

                logging.info(
                    "Topic-Konfiguration aktualisiert: +%d / -%d",
                    len(added),
                    len(removed),
                )

            except (OSError, json.JSONDecodeError, ValueError) as exc:
                logging.error(
                    "Konfiguration konnte nicht neu geladen werden: %s",
                    exc,
                )
            except Exception:
                logging.exception(
                    "Unerwarteter Fehler beim Topic-Reload"
                )
    source.on_connect = on_source_connect
    source.on_subscribe = on_source_subscribe
    source.on_message = on_source_message
    source.reconnect_delay_set(min_delay=1, max_delay=60)

    stop_event = threading.Event()

    reload_thread = threading.Thread(
        target=reload_config_loop,
        name="bayrol-config-reload",
        daemon=True,
    )
    try:
        destination.connect(
            destination_cfg["host"],
            int(destination_cfg["port"]),
            keepalive=60,
        )
        destination.loop_start()

        logging.info(
            "Verbinde mit Bayrol: %s:%s",
            source_cfg["host"],
            source_cfg["port"],
        )
        source.connect(
            source_cfg["host"],
            int(source_cfg["port"]),
            keepalive=60,
        )
        reload_thread.start()
        source.loop_forever()

    except KeyboardInterrupt:
        logging.info("Bridge wird beendet.")
    except (OSError, ValueError, mqtt.MQTTException) as exc:
        logging.error("Verbindungsfehler: %s", exc)
        return 1

    finally:
        stop_event.set()

        if reload_thread.is_alive():
            reload_thread.join(timeout=3)

        source.disconnect()
        destination.loop_stop()
        destination.disconnect()

        logging.info("Bayrol PoolAccess Bridge beendet.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
