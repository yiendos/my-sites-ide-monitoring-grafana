# Grafana

[Grafana](https://grafana.com/oss/grafana/) for [my-sites-ide](https://github.com/yiendos/my-sites-ide):
dashboards and Explore at http://localhost:3000, wired to whichever of the IDE's other monitoring
plugins are installed - Prometheus for metrics, Loki for logs, Tempo for traces - with links
between them.

Written for: developers running sites in my-sites-ide who want to see their sites' metrics, logs
and traces in one place.

## Contents

- [Installation](#installation)
- [The monitoring plugins](#the-monitoring-plugins)
- [Data sources](#data-sources)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section
of the IDE's `composer.local.json` - or add
[`yiendos/my-sites-ide-preset-monitoring`](https://github.com/yiendos/my-sites-ide-preset-monitoring)
instead, for the whole monitoring stack:

```json
"yiendos/my-sites-ide-monitoring-grafana": "@dev"
```

Then, from the IDE root:

```
composer update
php my-sites-ide monitoring:grafana-start
```

Grafana is opt-in: it doesn't autostart. Start it with `monitoring:grafana-start`, which writes its
data sources first - start the other monitoring plugins before it.

Then log in at http://localhost:3000 as `admin`, password `admin` (or `GRAFANA_ADMIN_PASSWORD`, if
you set it before the first start). Grafana offers to change it straight away;
`monitoring:grafana-password` changes it any time after.

## The monitoring plugins

| Plugin | Service | Role |
|---|---|---|
| **monitoring-grafana** | `grafana` | the UI - this plugin |
| [monitoring-prometheus](https://github.com/yiendos/my-sites-ide-monitoring-prometheus) | `prometheus` | metrics |
| [monitoring-loki](https://github.com/yiendos/my-sites-ide-monitoring-loki) | `loki` | log storage |
| [monitoring-tempo](https://github.com/yiendos/my-sites-ide-monitoring-tempo) | `tempo` | traces |
| [monitoring-alloy](https://github.com/yiendos/my-sites-ide-monitoring-alloy) | `alloy` | collector - container logs, and your apps' OpenTelemetry on `alloy:4318` |

None depends on another; each works with whichever of the others are installed.

```
your apps --OTLP--> alloy --traces--> tempo --service graphs--> prometheus
                       |--logs------> loki                          ^
                       |--metrics-----------------------------------|
  IDE containers' logs-'
                                  grafana --queries--> prometheus, loki, tempo
```

## Data sources

`monitoring:grafana-start` reads the IDE's plugin list (`_dev/cache/plugins.php`) and writes
`storage/plugins/grafana/provisioning/datasources/my-sites-ide.yaml` with one data source per
installed plugin:

| Data source | URL | Linked to |
|---|---|---|
| Prometheus (`uid: prometheus`) | `http://prometheus:9090` | exemplars -> the trace in Tempo |
| Loki (`uid: loki`) | `http://loki:3100` | a log line's `trace_id` -> the trace in Tempo |
| Tempo (`uid: tempo`) | `http://tempo:3200` | a span -> its logs in Loki and its metrics in Prometheus, plus the service graph |

Links only appear when both ends are installed. The first data source installed, in that order,
is the default. Remove a monitoring plugin and run `monitoring:grafana-start` again: its data
source is deleted rather than left pointing at nothing.

Data sources are provisioned, so they can't be edited in the UI - add your own alongside them.

## Command reference

| Command | What it does |
|---|---|
| `monitoring:grafana-start` | Writes the data sources for the monitoring plugins installed, then `docker compose up -d --build grafana`. Restarts a running Grafana when the data sources changed, and recreates one whose compose config changed (e.g. a new `GRAFANA_PORT`) |
| `monitoring:grafana-stop` | `docker compose stop grafana`, leaving the rest of the IDE running |
| `monitoring:grafana-password [password]` | Sets the `admin` user's password, asking for it (hidden) when it's left out. Grafana has to be running |

## Configuration

| Variable | Default | What it does |
|---|---|---|
| `GRAFANA_PORT` | `3000` (this plugin's `.env`) | The host port for the web UI |
| `GRAFANA_ANONYMOUS` | `false` | `true` also lets anyone in as Admin without logging in. The login form stays either way |
| `GRAFANA_ADMIN_PASSWORD` | `admin` | The `admin` user's first password - only applied when Grafana creates its database. Change it afterwards with `monitoring:grafana-password` |

Set any of them in the IDE's root `.env`, which wins over the plugin's defaults, then run
`monitoring:grafana-start`. `php my-sites-ide ide:plugin-env yiendos/my-sites-ide-monitoring-grafana`
copies them in, commented out.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `NAMESPACE` (root `.env`) | the image name, `${NAMESPACE}_grafana` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | finding the plugin list and storage |
| `_dev/cache/plugins.php` (written by `Plugins\Discover`) | which monitoring plugins are installed |
| `storage/plugins/grafana/` (`"storage": true`) | Grafana's database, plugins and provisioning |
| the `my-sites-ide` network | reaching `prometheus`, `loki` and `tempo` |

The container carries `prometheus.io/scrape` labels, so the prometheus plugin scrapes Grafana's
own metrics.

## Troubleshooting

**A data source is missing, or one for a removed plugin is still there.** The data sources are
written when Grafana starts: run `monitoring:grafana-start` after `composer update` adds or
removes a monitoring plugin.

**A data source test fails.** Its service isn't running - start it with
`monitoring:<service>-start`.

**Port 3000 is already allocated.** `docker ps --filter publish=3000` shows which container has it,
or move Grafana with `GRAFANA_PORT`.

**`admin` doesn't log in.** The admin password is only set when Grafana creates its database;
`GRAFANA_ADMIN_PASSWORD` changes made later don't apply. Set it with
`php my-sites-ide monitoring:grafana-password`.

## Known gaps

- Anonymous access (`GRAFANA_ANONYMOUS=true`) is rough in Grafana 13: it shows "not authorised"
  for per-user features such as stars and teams, and Grafana logs that anonymous roles other than
  Viewer are deprecated - a future version may limit it to Viewer, which can't use Explore.
- With anonymous access on, anyone who can reach port 3000 on your machine is a Grafana Admin, and
  can query every log and metric in the IDE.
- On Linux hosts, `storage/plugins/grafana/` is created by your user while Grafana runs as uid 472,
  so it may not be able to write there. Docker Desktop on macOS maps ownership, so it isn't
  affected.
- No dashboards are provisioned yet - Explore and the Tempo service graph work out of the box.
