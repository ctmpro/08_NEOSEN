# Utilitaires

Bibliothèques tierces à déposer ici (non versionnées par défaut si vous le souhaitez) :

```
utilitaires/
├── PHPMailer/          ← archive officielle PHPMailer
│   └── src/
│       ├── Exception.php
│       ├── PHPMailer.php
│       └── SMTP.php
└── ReCaptcha/          ← bibliothèque google/recaptcha
    └── src/autoload.php   (ou directement ReCaptcha.php + sous-dossiers)
```

Structures reconnues automatiquement :

- **PHPMailer** : `PHPMailer/src/PHPMailer.php` ou `PHPMailer/PHPMailer.php` (voir `includes/mailer.php`).
  Si absent, le site se rabat sur la fonction `mail()` de PHP.
- **ReCaptcha** : `ReCaptcha/src/autoload.php`, `ReCaptcha/autoload.php`, ou un dossier contenant `ReCaptcha.php`
  (voir `includes/recaptcha.php`). Si absent, la vérification se fait par appel direct à l'API Google.

Ce dossier est protégé par un `.htaccess` (aucun accès HTTP direct).
