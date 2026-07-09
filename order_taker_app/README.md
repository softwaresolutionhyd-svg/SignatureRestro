# Order Taker Flutter App

Mobile app for waiters to take orders — connects to your Laravel backend.

## Setup

1. Install [Flutter SDK](https://docs.flutter.dev/get-started/install) and add it to PATH.

2. If this folder has no `android/` or `ios/` folders yet, generate platform files:

```bash
cd order_taker_app
flutter create . --org com.softwaresolution --project-name order_taker_app
```

3. Install dependencies:

```bash
flutter pub get
```

4. Run on device/emulator:

```bash
flutter run
```

## Server URL (fixed LAN)

Cafe PC fixed IP: **`http://192.168.3.50`** (same WiFi required).

1. On the PC, run once as **Administrator**: `scripts\set-cafe-lan-ip.ps1` from the project root.
2. In the app login screen, Server URL: `http://192.168.3.50`

Tablet browser: Kitchen `http://192.168.3.50/kitchen`, Order Status `http://192.168.3.50/order-status`

API endpoints used: `/api/login`, `/api/order-taker/*`

## Login

Use the same email/password as the web app. User must have **Order Taker** module permission.

## Android HTTP (local network)

Cleartext HTTP is enabled for local development in `android/app/src/main/AndroidManifest.xml`.

For production, use HTTPS and remove `usesCleartextTraffic`.
