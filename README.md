# OPNgraph

A real-time firewall traffic visualizer for [OPNsense](https://opnsense.org/). OPNgraph pulls live firewall logs, aliases, and rules via the OPNsense API and renders them as an interactive force-directed graph using D3.js.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)
![D3.js](https://img.shields.io/badge/D3.js-v7-F9A03C?logo=d3.js&logoColor=white)
![License](https://img.shields.io/badge/license-MIT-green)

## Features

- **Live traffic graph** — nodes represent firewall aliases, IPs, and subnets; edges represent observed connections
- **Three layout modes** — Radial, Force-directed, and Hierarchical (Flow)
- **Particle animation** — animated particles flow along connection paths
- **Colour-coded connections** — green (alias-matched pass), orange (unknown-source pass), red (blocked/denied)
- **Smart IP aggregation** — groups IPs into /24 subnets when multiple IPs share a range
- **Live activity feed** — real-time scrolling log of recent firewall events
- **Top talkers panel** — ranked view of top sources, destinations, and ports
- **Search & filters** — filter by connection type, node type, or search for specific nodes
- **Focus mode** — click any node to isolate its connections
- **Auto-polling** — configurable refresh interval keeps the graph up to date
- **Well-known port labels** — automatically labels common ports (SSH, HTTP, DNS, etc.)

## Requirements

- PHP 8.0+ with the `curl` extension enabled
- A web server (Apache, Nginx, etc.)
- An OPNsense firewall with API access enabled

## Setup

### 1. Clone the repository

```bash
git clone https://github.com/ghostinthecable/opngraph.git
cd opngraph
```

### 2. Configure your OPNsense connection

Edit `config.php` with your OPNsense host and API credentials:

```php
return [
    'host'          => 'https://your-opnsense-ip',
    'api_key'       => 'YOUR_API_KEY',
    'api_secret'    => 'YOUR_API_SECRET',
    'verify_ssl'    => false,   // set true if using a trusted certificate
    'log_limit'     => 500,     // max log entries per poll (up to 2000)
    'poll_interval' => 5000,    // frontend refresh interval in ms
];
```

> **Generating API credentials:** In OPNsense, go to **System > Access > Users**, select your user, scroll to **API keys**, and click **+** to generate a key/secret pair.

### 3. Deploy to your web server

Point your web server's document root (or a virtual host) at the `opngraph` directory. For example with Apache:

```apache
<VirtualHost *:80>
    DocumentRoot /var/www/html/opngraph
    <Directory /var/www/html/opngraph>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Or simply drop the files into an existing web root:

```bash
cp -r opngraph /var/www/html/
```

### 4. Open in your browser

Navigate to `http://your-server/opngraph` and the graph should begin populating with live firewall data.

## Usage

| Control | Action |
|---------|--------|
| **Scroll** | Zoom in/out |
| **Click + drag** | Pan the graph |
| **Click a node** | Focus mode — isolate that node's connections |
| **Drag a node** | Reposition it |
| **Escape** | Clear search / exit focus mode |
| **Radial / Force / Flow** | Switch graph layout |
| **Activity** | Toggle live event feed |
| **Talkers** | Toggle top sources/destinations/ports panel |
| **Filters** | Show/hide connection types and node categories |
| **Pause / Resume** | Stop/start live polling |
| **Reset** | Reset zoom and exit focus mode |

## Project Structure

```
opngraph/
├── index.php      # Frontend — full UI, D3.js graph, and all client-side logic
├── api.php        # Backend — proxies OPNsense API calls, builds graph data
├── config.php     # Configuration — host, credentials, and polling settings
└── README.md
```

## Security Notes

- **Do not expose this tool to the public internet** — it proxies authenticated OPNsense API calls from the server side
- Keep `config.php` out of version control if it contains real credentials (add it to `.gitignore`)
- Use `verify_ssl => true` in production with a properly signed certificate

---

<p align="center">
  <sub>Conceived by a mass of mass-less photons trapped in fibre (a.k.a. <a href="https://github.com/ghostinthecable">ghostinthecable</a>), brought into existence by <a href="https://claude.ai">Claude</a> — who mass-produced mass amounts of code while the human mass-consumed mass amounts of coffee.</sub>
</p>
