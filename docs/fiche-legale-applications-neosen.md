# Fiche juridique — Applications éditées par NEOSEN

> **Mode d'emploi :** copiez tout ce document dans votre IA, remplissez la **partie 3** pour l'application concernée, puis envoyez la **partie 4** (consignes). Les parties 1 et 2 ne changent pas d'une application à l'autre.
> Les documents produits sont une base de travail : pour une application qui traite des données sensibles (santé, vie intime, mineurs, géolocalisation précise…), faites-les relire par un juriste.

---

## 1. Éditeur (identique pour toutes les applications)

| Information | Valeur |
|---|---|
| Dénomination sociale | **NEOSEN** |
| Forme juridique | Société par actions simplifiée unipersonnelle (SASU) |
| Siège social | 47 rue Vivienne, 75002 Paris, France |
| RCS | 131 568 255 R.C.S. Paris |
| SIREN | 131 568 255 |
| SIRET | 131 568 255 00015 |
| EUID | FR7501.131568255 |
| TVA intracommunautaire | FR40131568255 |
| Code NAF / APE | 6201Z — Programmation informatique |
| D-U-N-S | 28-850-4099 |
| Directeur de la publication | « Le Président de la société NEOSEN » (ne pas publier de nom de personne) |
| **Email de contact (unique pour tout)** | **contact@neosen.tech** |
| Téléphone | +33 7 88 40 51 21 |
| Site de l'éditeur | https://neosen.tech |
| Délégué à la protection des données (DPO) | Pas de DPO désigné (non obligatoire). Contact données personnelles : **contact@neosen.tech** |
| Autorité de contrôle | CNIL — 3 place de Fontenoy, TSA 80715, 75334 Paris Cedex 07 — www.cnil.fr |
| Droit applicable / tribunaux | Droit français ; tribunaux compétents de Paris (sous réserve des règles protectrices du consommateur) |
| Médiateur de la consommation | **[À COMPLÉTER — obligatoire si l'application vend à des particuliers : nom, adresse et site du médiateur auquel NEOSEN adhère]** |

## 2. Prestataires habituels (à garder seulement s'ils sont utilisés)

| Rôle | Prestataire | Localisation |
|---|---|---|
| Hébergement du site / de l'API | OVH SAS — 2 rue Kellermann, 59100 Roubaix, France — RCS Lille Métropole 424 761 419 — www.ovhcloud.com | France (UE) |
| Paiement par carte | Stripe Payments Europe, Ltd. — 1 Grand Canal Street Lower, Grand Canal Dock, Dublin, D02 H210, Irlande | UE (transferts possibles vers les États-Unis) |
| Paiement Mobile Money (Afrique de l'Ouest) | PayDunya — Dakar, Sénégal (Wave, Orange Money, Free Money, carte…) | Sénégal (hors UE) |
| Distribution iOS / achats intégrés | Apple Distribution International Ltd. (App Store) | Irlande / États-Unis |
| Distribution Android / achats intégrés | Google Ireland Ltd. (Google Play) | Irlande / États-Unis |
| Notifications push | [Expo Push / Firebase Cloud Messaging (Google) / Apple Push Notification service — à préciser] | États-Unis |
| Emails transactionnels | [Serveur SMTP OVH ou autre — à préciser] | |
| Statistiques, crash, IA, cartes… | [À préciser par application : ex. Firebase Analytics, Sentry, OpenAI / Anthropic, Google Maps…] | |

## 3. Fiche de l'application (à remplir à chaque fois)

```
Nom de l'application :
Site web / URL de l'application :
Plateformes : [iOS / Android / Web]
Description en 2-3 phrases :
Public visé : [particuliers / entreprises / les deux]   Pays principaux : [France, Sénégal, …]
Âge minimum : [ex. 18 ans / 16 ans / 13 ans]
Création de compte : [oui/non — email, téléphone, Google, Apple…]
Suppression de compte : [depuis l'app (obligatoire Apple) + page web de suppression (obligatoire Google) : URL]

Données collectées (cocher / préciser) :
- Identité : nom, prénom, photo, date de naissance…
- Contact : email, téléphone
- Contenus créés par l'utilisateur : messages, photos, publications…
- Localisation : [aucune / approximative / précise]
- Données de paiement : [traitées uniquement par Stripe / PayDunya / Apple / Google — NEOSEN ne voit pas les numéros de carte]
- Données techniques : appareil, identifiants, journaux, adresse IP
- Données sensibles (santé, vie intime, orientation, religion…) : [aucune / préciser]
- Contacts du téléphone, micro, caméra, notifications : [préciser]

Finalités : [fournir le service, gestion du compte, paiement, support, sécurité, statistiques, marketing…]
Publicité / suivi entre applications (ATT iOS) : [oui/non]
Cookies ou traceurs (version web) : [aucun hors nécessaires / mesure d'audience / publicité]
Durées de conservation : [ex. compte actif + 3 ans d'inactivité ; factures 10 ans ; journaux 1 an]
Prestataires utilisés (voir partie 2) :
Transferts hors UE : [oui/non — vers quels pays / prestataires]

Modèle économique : [gratuit / achat unique / abonnement / achats intégrés / prestation]
Prix et périodicité :
Moyens de paiement : [achats intégrés Apple/Google, Stripe (web), PayDunya]
Résiliation / remboursement : [modalités]
Contenus publiés par les utilisateurs et modération : [oui/non — règles, signalement, blocage]
Fonctionnalités d'IA : [oui/non — fournisseur, données envoyées]
Particularités : [ex. application de rencontre / couple, santé, mineurs, géolocalisation en temps réel…]
```

## 4. Consignes pour l'IA

Tu es juriste spécialisé en droit du numérique (France / Union européenne). À partir des parties 1, 2 et 3, rédige en **français**, de façon claire et lisible par un non-juriste, les documents suivants pour l'application décrite :

1. **Mentions légales** (LCEN, art. 6) : éditeur, directeur de la publication, hébergeur, contact.
2. **Conditions générales d'utilisation (CGU)** : objet, accès et compte, règles d'usage, contenus des utilisateurs et modération, propriété intellectuelle, responsabilité, suspension/suppression de compte, modification des CGU, droit applicable, médiation, contact.
3. **Conditions générales de vente (CGV)** — uniquement si l'application est payante : prix TTC, abonnement et renouvellement automatique, résiliation, **droit de rétractation de 14 jours** et son exception pour les contenus numériques fournis immédiatement avec accord exprès (art. L221-28 du Code de la consommation), remboursements, achats via Apple/Google soumis à leurs propres conditions, médiateur de la consommation.
4. **Politique de confidentialité** (RGPD, art. 13) : responsable de traitement (NEOSEN), données collectées, finalités et **bases légales** pour chaque finalité, destinataires et sous-traitants, transferts hors UE et garanties (clauses contractuelles types, Data Privacy Framework), durées de conservation, sécurité, droits (accès, rectification, effacement, opposition, limitation, portabilité, retrait du consentement, directives post-mortem), exercice des droits à **contact@neosen.tech**, réclamation auprès de la CNIL, mineurs. Si des utilisateurs sont au Sénégal, mentionne aussi la loi sénégalaise n° 2008-12 sur les données personnelles et la CDP (Commission de protection des données personnelles).
5. **Politique cookies / traceurs** — si une version web existe.
6. **Page de suppression de compte et des données** (exigée par Google Play, et suppression depuis l'app exigée par Apple) : étapes dans l'app, et alternative par email à contact@neosen.tech.
7. **Réponses aux questionnaires des stores** :
   - Apple App Store — « Confidentialité de l'app » (étiquettes de données : données liées / non liées à l'utilisateur, suivi) ;
   - Google Play — « Sécurité des données » (collecte, partage, chiffrement, suppression).

**Règles à respecter :**
- Utilise exactement les informations des parties 1 à 3, sans inventer de données, de prestataires ou de durées. Quand une information manque, écris **[À COMPLÉTER : …]**.
- Une seule adresse de contact partout : **contact@neosen.tech**.
- Discrétion : ne publie **aucun nom de personne** (ni dirigeant, ni associé) et n'indique pas le capital social dans les documents destinés au public.
- N'affirme rien que l'application ne fait pas (ex. « nous ne partageons jamais vos données » si un prestataire en reçoit).
- Si l'application traite des données sensibles (art. 9 RGPD) ou s'adresse à des mineurs, signale-le en tête de ta réponse, avec les obligations supplémentaires (consentement explicite, analyse d'impact AIPD, âge de consentement numérique de 15 ans en France).
- Respecte les règles des stores : Apple App Review Guidelines (5.1.1 confidentialité, 3.1 achats intégrés) et règles Google Play (données utilisateur, paiements).
- Indique en fin de chaque document sa date de mise à jour.
- Fournis chaque document séparément, en Markdown, prêt à être publié sur une page web.
