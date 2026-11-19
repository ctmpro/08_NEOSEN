# Déploiement automatique (GitHub Actions → OVH)

Chaque fois que du code arrive sur la branche **main** (par exemple en fusionnant une pull request), GitHub :

1. vérifie la syntaxe de tous les fichiers PHP (le déploiement s'arrête en cas d'erreur) ;
2. génère les CSS/JS minifiés ;
3. envoie **uniquement les fichiers modifiés** sur OVH par FTP ;
4. met à jour la base de données (migrations) via `api/deploy-hook.php` ;
5. vérifie que la page d'accueil répond.

Le suivi se fait dans l'onglet **Actions** du dépôt. On peut aussi relancer un déploiement à la main : **Actions → Déploiement OVH → Run workflow**.

## Ce qui n'est jamais envoyé ni modifié sur le serveur
- le fichier `.env` du serveur (vos mots de passe et clés restent uniquement sur OVH) ;
- le dossier `uploads/` (images, documents et factures envoyés depuis l'admin) ;
- `docs/`, `tools/`, `.github/`, `README.md`.

## Secrets GitHub (Settings → Secrets and variables → Actions)
| Secret | Exemple |
|---|---|
| `FTP_SERVER` | `ftp.cluster0XX.hosting.ovh.net` |
| `FTP_USERNAME` | identifiant FTP OVH |
| `FTP_PASSWORD` | mot de passe FTP OVH |
| `FTP_PROTOCOL` | `ftps` (recommandé) ou `ftp` |
| `FTP_SERVER_DIR` | `www/` (avec la barre oblique finale) |
| `SITE_URL` | `https://neosen.tech` |
| `DEPLOY_INSTALL_TOKEN` | longue chaîne aléatoire, **identique** à celle du `.env` du serveur |

## Sur le serveur OVH
Ajouter dans le fichier `.env` :

```
DEPLOY_INSTALL_TOKEN=la-même-valeur-que-le-secret-github
```

Sans cette ligne, le déploiement fonctionne quand même : la base sera mise à jour à la prochaine ouverture de l'administration.

## Premier déploiement
Le premier passage renvoie tous les fichiers (GitHub ne sait pas encore ce qui est déjà sur le serveur), puis il mémorise l'état dans `.ftp-deploy-sync-state.json` sur le serveur : les suivants n'envoient que les différences. Ne supprimez pas ce fichier.
