# LoxBerry Plugin – Bayrol PoolAccess

A LoxBerry plugin that connects Bayrol PoolAccess to a local MQTT broker, making pool measurements available to Loxone and other home automation systems.

## Features

- Connects to Bayrol PoolAccess using the configured account credentials.
- Receives pool measurement updates from the PoolAccess service.
- Publishes the received values to a local MQTT broker.
- Provides a LoxBerry web interface for configuration.
- Runs automatically as a systemd service.
- Restarts the service after configuration changes.
- Preserves the configuration during plugin upgrades.

## Requirements

- LoxBerry with Python 3 support
- Network access to Bayrol PoolAccess
- Local MQTT broker, such as the LoxBerry MQTT Gateway
- Bayrol PoolAccess account and compatible pool controller

## Installation

1. Download the plugin ZIP archive from the GitHub Releases page.
2. Open the LoxBerry plugin management interface.
3. Install the plugin using the ZIP archive.
4. Open **Bayrol PoolAccess** in the LoxBerry plugin menu.
5. Enter your Bayrol PoolAccess credentials.
6. Configure the MQTT broker and the PoolAccess topic prefix.
7. Save the configuration.

The plugin installs a systemd service named `bayrolpoolaccess.service`.

## Configuration

The web interface allows you to configure the PoolAccess connection, MQTT destination and subscribed measurement topics.

The file `config/config.example.json` provides an example configuration.

Personal credentials are stored locally in:

`/opt/loxberry/config/plugins/bayrolpoolaccess/config.json`

Do not publish this file or commit it to Git.

## MQTT Integration

PoolAccess measurement updates are forwarded to the configured local MQTT broker.

Example MQTT topic:

`Bayrol/d02/DEVICE-ID/v/4.98`

The exact topics depend on your Bayrol device and plugin configuration.

The published MQTT values can be used in Loxone, Home Assistant or other MQTT-compatible systems.

## Service Management

Check the service status:

```bash
sudo systemctl status bayrolpoolaccess.service
```

View the service logs:

```bash
sudo journalctl -u bayrolpoolaccess.service -f
```

Restart the service:

```bash
sudo systemctl restart bayrolpoolaccess.service
```

## Security

- Bayrol credentials remain on the local LoxBerry installation.
- The configuration file is excluded from the Git repository.
- The web interface uses LoxBerry authentication.
- Service restart permission is restricted to the specific Bayrol systemd service.

## Disclaimer

This is an independent community project and is not affiliated with or endorsed by BAYROL.

Bayrol PoolAccess is a service of its respective owner. Changes to the PoolAccess service may affect plugin functionality.

## Author

Sven Lanwer

## Source Code

https://github.com/SvenLanwer/LoxBerry-Plugin-BayrolPoolAccess