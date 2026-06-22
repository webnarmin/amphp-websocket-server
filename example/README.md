# Example Setup

This directory contains a router-based WebSocket server, a browser client and a CLI control HTTP broadcast script.

## Requirements

- PHP 8.2+
- Composer dependencies installed with `composer install`

## Run

Start the WebSocket server:

```bash
php example/server.php
```

Start the browser client:

```bash
php -S 127.0.0.1:8000 -t example/public
```

Open `http://127.0.0.1:8000/index.php`.

The browser sends strict JSON envelopes with `action`, `payload`, `requestId` and `metadata`. The demo registers `echo`, `sum` and `broadcast` actions through `ActionRouter`.

Run the control HTTP broadcast example:

```bash
php example/broadcast_from_cli.php
```
