# Signature Admin App

Flutter admin panel for Signature POS (managers / company admin).

## Setup

1. Install [Flutter SDK](https://docs.flutter.dev/get-started/install/windows) and add to PATH.
2. From this folder:

```powershell
flutter pub get
flutter run
```

## Login

- Server URL: same as Order Taker (e.g. `http://192.168.1.105:8080`)
- Username/password: admin / company_admin account
- App sends `app: admin` on `/api/login`

## Features (v1)

- Dashboard (today / month sales, pending, low stock)
- Pending bills
- Today's paid bills
- Kitchen cancelled items
- Expenses
- Low stock list

## API

Requires Laravel routes under `/api/admin/*` (Sanctum).
