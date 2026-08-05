# Signature Admin App

Flutter admin panel linked to **online hosting**:
`https://signature.softwaresolutions.pk`

## Setup

```powershell
flutter pub get
flutter build apk --release
```

APK: `releases/Signature-Admin.apk`

## Login

- Server URL (default): `https://signature.softwaresolutions.pk`
- Use hosting Admin / Company Admin account
- App sends `app: admin` on `/api/login`

## Note

Hosting pe Laravel Admin API deploy hona chahiye (`/api/admin/*` + login `app=admin`).
