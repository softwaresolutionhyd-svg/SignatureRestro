# Signature Management System

Laravel app for **signature.softwaresolutions.pk**

Changes are made in Cursor → pushed to GitHub → auto-deployed to hosting via FTP.

---

## GitHub Secrets (StackCP / cPanel)

Repo → **Settings → Secrets and variables → Actions**:

| Secret Name | Value |
|-------------|-------|
| `FTP_SERVER` | StackCP FTP host (e.g. `ftp.softwaresolutions.pk`) |
| `FTP_USERNAME` | `signature@softwaresolutions.pk` |
| `FTP_PASSWORD` | FTP account password |
| `FTP_SERVER_DIR` | `/` |

---

## Daily workflow

```
Cursor → code change → git push main → GitHub Actions → signature.softwaresolutions.pk
```

---

## Document root

Subdomain should point to:

```
public_html/signature/public
```

---

## Troubleshooting

| Problem | Fix |
|---------|-----|
| Deploy fail | Check secrets names and `FTP_SERVER_DIR` = `/` |
| 500 error | `chmod -R 775 storage bootstrap/cache` on server |
| 404 / blank page | Document root → `public_html/signature/public` |
| DB error | Check `.env` on server (not in repo) |
