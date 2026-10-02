# NEOSEN — Site vitrine & back-office

Site institutionnel et commercial de NEOSEN (**Web · Software · Data**), développé en PHP 8 / MySQL 8,
HTML5, CSS3 et JavaScript natif — **sans CMS ni framework**. Tout le contenu est administrable depuis `/admin`.

## Installation

1. **Prérequis** : PHP 8.1+ (extensions `pdo_mysql`, `gd` avec WebP, `fileinfo`, `mbstring`, `intl` conseillée),
   MySQL 8 ou MariaDB 10.5+, Apache avec `mod_rewrite` (Nginx : voir plus bas).
2. Copier `.env.example` en `.env` et renseigner les valeurs (base de données, SMTP, reCAPTCHA, `APP_URL`, `APP_KEY`).
3. Déposer **PHPMailer** et **ReCaptcha** dans `utilitaires/` (voir `utilitaires/README.md`).
4. Créer la base, les tables, les contenus initiaux et le compte administrateur :
   ```bash
   php install/install.php --admin-email=vous@domaine.com --admin-password="MotDePasseFort1" --admin-name="Votre nom"
   ```
   Sans accès SSH : renseigner `INSTALL_TOKEN` (et `INSTALL_ADMIN_*`) dans `.env`, ouvrir
   `https://votre-domaine/install/install.php?token=VOTRE_TOKEN`, puis **vider `INSTALL_TOKEN`**.
5. Rendre `uploads/` inscriptible par PHP.
6. En production : `APP_ENV=production`, `APP_DEBUG=false`, puis `php tools/minify.php`
   (à relancer après toute modification de `assets/css` ou `assets/js`). Activer la redirection HTTPS dans `.htaccess`.

Développement local : `php -S localhost:8000 index.php` avec `APP_URL=http://localhost:8000`.

L'installation importe les 5 réalisations (BADGEL, EKTO SEN, EXTRIUME, LINK2VEST, EASY-GROUPAGE) avec
leurs captures d'écran (`install/seed-images/`), les 3 services, la méthodologie, les arguments, les prestations
Data et les technologies. Elle ne remplace jamais des données existantes.

## Architecture

```
admin/        Back-office (login, dashboard, réalisations, services, blocs, technologies, demandes, paramètres, comptes)
api/          contact.php (formulaire), reorder.php (glisser-déposer admin)
assets/       css, js, images, fonts
config/       env.php (loadEnvFile), database.php (PDO), app.php, bootstrap.php, settings_schema.php
includes/     header, footer, fonctions, i18n, paramètres, accès aux données, icônes, uploads, mailer, recaptcha, partials/
lang/         Libellés d'interface (fr.php, en.php)
pages/        Vues publiques (accueil, à propos, services, réalisations, data, contact, légal, sitemap, robots…)
uploads/      Médias envoyés depuis l'admin (exécution PHP désactivée ; uploads/messages inaccessible publiquement)
utilitaires/  PHPMailer, ReCaptcha
install/      schema.sql, seed.php, install.php, seed-images/
tools/        minify.php
index.php     Contrôleur frontal (URL propres)
```

### URL

`/`, `/a-propos`, `/services`, `/services/{slug}`, `/realisations`, `/realisations/{slug}`, `/data`, `/contact`,
`/mentions-legales`, `/politique-de-confidentialite`, `/sitemap.xml`, `/robots.txt`, `/admin`.

## Tout est paramétrable

| Où dans l'admin | Ce qu'on y modifie |
|---|---|
| **Textes, médias & réglages** | Nom de la plateforme, slogan, domaine, logos, favicon, image Open Graph, couleurs, coordonnées, WhatsApp, réseaux sociaux, hero (texte, boutons, image ou **vidéo**), titres et textes de toutes les sections, À propos (texte, image, vidéo), page Data, page Contact (listes déroulantes, message de confirmation, emails), SEO de chaque page, Google Analytics, mentions légales et politique de confidentialité |
| **Réalisations** | Ajout / modification / suppression / masquage / mise en avant / réordonnancement, galerie multi-images, image principale, fiche détaillée, SEO |
| **Catégories** | Filtres « Tous / Web / Application / Plateforme / Data » |
| **Services** | Les expertises : nom, icône, image, prestations, ordre, statut, SEO |
| **Blocs de contenu** | Méthodologie, arguments « Pourquoi NEOSEN », chiffres clés, prestations Data, valeurs |
| **Technologies** | Technologies par catégorie, avec logo optionnel |
| **Demandes** | Lecture, notes internes, archivage, pièces jointes, export CSV |
| **Administrateurs / Mon compte** | Comptes, rôles, mots de passe |

Pour ajouter un nouveau texte éditable : déclarer le champ dans `config/settings_schema.php`
puis l'afficher avec `setting('ma_cle')`. Il apparaît automatiquement dans l'admin.

## Sécurité

- Données sensibles dans `.env` (bloqué par `.htaccess`), dossiers internes inaccessibles en HTTP.
- Requêtes préparées PDO partout ; échappement systématique des sorties ; HTML d'admin filtré.
- Mots de passe `password_hash`, sessions `HttpOnly`/`SameSite`/`Secure`, régénération d'ID, expiration après 2 h d'inactivité,
  blocage 15 min après 5 échecs de connexion (IP + email), CSRF sur tous les formulaires.
- Formulaire de contact : CSRF, pot de miel, délai minimal, 3 envois / 10 min / IP, reCAPTCHA v3 (ou v2),
  validation serveur, pièces jointes vérifiées par type MIME réel et stockées hors accès public.
- Uploads renommés aléatoirement, images ré-encodées en WebP, SVG filtrés, exécution de scripts désactivée dans `uploads/`.

## Performance & SEO

Aucune dépendance JS/CSS externe (hors Google Fonts). Images WebP redimensionnées, `loading="lazy"`, dimensions
explicites, scripts `defer`, CSS/JS minifiés en production, cache navigateur et compression via `.htaccess`,
animations désactivées si `prefers-reduced-motion`, canvas mis en pause hors écran.
Balises title/description par page, canonical, Open Graph, données structurées (Organization, WebSite, Service,
CreativeWork, BreadcrumbList), sitemap et robots dynamiques, slugs propres.

## Multilingue

Première version en français. Pour ajouter l'anglais : `APP_LANGS=fr,en` dans `.env`. Le routeur accepte alors
le préfixe `/en/…`, les libellés viennent de `lang/en.php`, et les tables de contenu (paramètres, services,
réalisations, catégories, blocs) possèdent une colonne `lang` : un sélecteur de langue apparaît dans l'admin pour saisir les contenus traduits (les paramètres non traduits reprennent la version française).

## Nginx

```nginx
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ ^/(config|includes|pages|lang|tools|utilitaires|uploads/messages)/ { deny all; }
location ~ ^/install/(?!install\.php) { deny all; }
location ~ /\.env { deny all; }
location ~ ^/uploads/.*\.php$ { deny all; }
```
