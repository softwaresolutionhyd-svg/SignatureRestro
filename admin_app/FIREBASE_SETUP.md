# Firebase / FCM setup — Stair Admin App

Push notifications require a real Firebase project. Placeholders let the project compile; replace them before production.

## 1. Firebase Console

1. Open [Firebase Console](https://console.firebase.google.com/) → Create project (or use existing).
2. Add an **Android** app with package name: `com.softwaresolution.admin_app`
3. Download **`google-services.json`** → put at:
   `admin_app/android/app/google-services.json`
4. Project settings → **Service accounts** → Generate new private key → save as:
   `storage/app/firebase-credentials.json` (Laravel root)
5. Copy the Firebase **Web app** config (or run FlutterFire) into:
   `admin_app/lib/firebase_options.dart`

## 2. FlutterFire (recommended)

```bash
cd admin_app
dart pub global activate flutterfire_cli
flutterfire configure --project=YOUR_PROJECT_ID --platforms=android
```

This updates `google-services.json` and `lib/firebase_options.dart`.

## 3. Laravel `.env`

```env
FCM_ENABLED=true
FCM_PROJECT_ID=YOUR_FIREBASE_PROJECT_ID
FCM_CREDENTIALS=
FCM_ANDROID_CHANNEL_ID=stair_pos_orders
```

Leave `FCM_CREDENTIALS` empty to use `storage/app/firebase-credentials.json`.

## 4. Migrate & clear config

```bash
php artisan migrate --force
php artisan config:clear
```

## 5. Rebuild APK

```bash
cd admin_app
flutter pub get
flutter build apk --release
```

After login, the app registers the FCM token at `POST /api/admin/device-tokens`.
POS events (new/update/pay/refund/cancel/void/delete) send FCM via `PosActivityNotifier`.
