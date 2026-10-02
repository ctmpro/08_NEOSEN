<?php
/**
 * Schéma des paramètres éditables depuis le back-office.
 *
 * Chaque groupe devient un onglet de la page « Paramètres & contenus ».
 * Types : text, textarea, html, url, email, tel, color, image, video, lines, bool, select
 * La valeur `default` est utilisée tant qu'aucune valeur n'a été enregistrée en base.
 *
 * Pour ajouter un contenu éditable : ajoutez un champ ici puis utilisez setting('cle') dans une vue.
 */

return [

    /* ================================================================
     * IDENTITÉ
     * ================================================================ */
    'general' => [
        'label'  => 'Identité & marque',
        'icon'   => 'layers',
        'fields' => [
            'site_name'        => ['type' => 'text', 'label' => 'Nom de la plateforme', 'default' => 'NEOSEN'],
            'site_tagline'     => ['type' => 'text', 'label' => 'Slogan', 'default' => 'Web. Software. Data.'],
            'site_domain'      => ['type' => 'text', 'label' => 'Nom de domaine affiché', 'default' => 'neosen.sn', 'help' => 'Affiché dans le site (l\'URL technique est définie par APP_URL dans le fichier .env).'],
            'site_description' => ['type' => 'textarea', 'label' => 'Courte présentation (footer)', 'default' => 'Studio technologique spécialisé dans la création de sites web, le développement d\'applications et les solutions Data & Business Intelligence.'],
            'logo'             => ['type' => 'image', 'label' => 'Logo (fond clair)', 'default' => 'assets/images/logo.svg'],
            'logo_light'       => ['type' => 'image', 'label' => 'Logo (fond sombre)', 'default' => 'assets/images/logo-light.svg'],
            'logo_height'      => ['type' => 'text', 'label' => 'Hauteur du logo (px)', 'default' => '34'],
            'favicon'          => ['type' => 'image', 'label' => 'Favicon', 'default' => 'assets/images/favicon.svg'],
            'og_image'         => ['type' => 'image', 'label' => 'Image de partage (Open Graph)', 'default' => 'assets/images/og-default.jpg', 'help' => 'Format recommandé : 1200 × 630 px.'],
            'color_primary'    => ['type' => 'color', 'label' => 'Couleur principale', 'default' => '#3B4BFF'],
            'color_accent'     => ['type' => 'color', 'label' => 'Couleur d\'accent', 'default' => '#14C8B4'],
            'color_dark'       => ['type' => 'color', 'label' => 'Couleur sombre (textes, sections foncées)', 'default' => '#0A0F1F'],
            'copyright'        => ['type' => 'text', 'label' => 'Mention copyright', 'default' => '{year} {site}. Tous droits réservés.', 'help' => '{year} et {site} sont remplacés automatiquement.'],
            'show_whatsapp_float' => ['type' => 'bool', 'label' => 'Afficher le bouton WhatsApp flottant', 'default' => '1'],
        ],
    ],

    /* ================================================================
     * COORDONNÉES
     * ================================================================ */
    'contact' => [
        'label'  => 'Coordonnées',
        'icon'   => 'phone',
        'fields' => [
            'contact_email'            => ['type' => 'email', 'label' => 'Email de contact (affiché)', 'default' => 'contact@neosen.sn'],
            'notification_email'       => ['type' => 'email', 'label' => 'Email de réception des demandes', 'default' => 'contact@neosen.sn', 'help' => 'Plusieurs adresses possibles, séparées par des virgules.'],
            'contact_phone'            => ['type' => 'tel', 'label' => 'Téléphone', 'default' => '+221 77 000 00 00'],
            'contact_whatsapp'         => ['type' => 'tel', 'label' => 'Numéro WhatsApp (format international)', 'default' => '+221770000000'],
            'contact_whatsapp_message' => ['type' => 'text', 'label' => 'Message WhatsApp pré-rempli', 'default' => 'Bonjour NEOSEN, je souhaite échanger sur un projet.'],
            'contact_address'          => ['type' => 'text', 'label' => 'Adresse / Localisation', 'default' => 'Dakar, Sénégal'],
            'contact_hours'            => ['type' => 'text', 'label' => 'Horaires', 'default' => 'Du lundi au vendredi, 9h – 18h'],
            'contact_map_embed'        => ['type' => 'url', 'label' => 'URL d\'intégration Google Maps (optionnel)', 'default' => ''],
        ],
    ],

    /* ================================================================
     * RÉSEAUX SOCIAUX
     * ================================================================ */
    'social' => [
        'label'  => 'Réseaux sociaux',
        'icon'   => 'users',
        'fields' => [
            'social_linkedin'  => ['type' => 'url', 'label' => 'LinkedIn', 'default' => ''],
            'social_instagram' => ['type' => 'url', 'label' => 'Instagram', 'default' => ''],
            'social_facebook'  => ['type' => 'url', 'label' => 'Facebook', 'default' => ''],
            'social_github'    => ['type' => 'url', 'label' => 'GitHub', 'default' => ''],
            'social_x'         => ['type' => 'url', 'label' => 'X (Twitter)', 'default' => ''],
            'social_youtube'   => ['type' => 'url', 'label' => 'YouTube', 'default' => ''],
        ],
    ],

    /* ================================================================
     * ACCUEIL — HERO
     * ================================================================ */
    'hero' => [
        'label'  => 'Accueil — Hero',
        'icon'   => 'rocket',
        'fields' => [
            'hero_eyebrow'    => ['type' => 'text', 'label' => 'Sur-titre', 'default' => 'Studio digital · Web · Software · Data'],
            'hero_title'      => ['type' => 'text', 'label' => 'Titre principal', 'default' => 'Nous transformons vos idées en *solutions digitales*.', 'help' => 'Entourez un mot avec des *astérisques* pour le mettre en valeur (dégradé).'],
            'hero_subtitle'   => ['type' => 'textarea', 'label' => 'Texte secondaire', 'default' => 'Sites web, applications, logiciels et solutions Data : NEOSEN conçoit des outils numériques adaptés à vos besoins et à vos ambitions.'],
            'hero_cta1_label' => ['type' => 'text', 'label' => 'Bouton principal — libellé', 'default' => 'Démarrer un projet'],
            'hero_cta1_link'  => ['type' => 'text', 'label' => 'Bouton principal — lien', 'default' => 'contact', 'help' => 'Chemin interne (ex : contact), URL complète, ou « whatsapp ».'],
            'hero_cta2_label' => ['type' => 'text', 'label' => 'Bouton secondaire — libellé', 'default' => 'Découvrir nos réalisations'],
            'hero_cta2_link'  => ['type' => 'text', 'label' => 'Bouton secondaire — lien', 'default' => 'realisations'],
            'hero_badges'     => ['type' => 'lines', 'label' => 'Points forts sous les boutons', 'default' => "Sur mesure\nDe l'idée à la mise en production\nWeb · Mobile · Data"],
            'hero_visual'     => ['type' => 'select', 'label' => 'Visuel du hero', 'default' => 'animation', 'options' => ['animation' => 'Animation (code · interfaces · données)', 'image' => 'Image', 'video' => 'Vidéo']],
            'hero_image'      => ['type' => 'image', 'label' => 'Image du hero (si « Image »)', 'default' => ''],
            'hero_video'      => ['type' => 'video', 'label' => 'Vidéo du hero (si « Vidéo », MP4/WebM)', 'default' => ''],
            'hero_video_poster' => ['type' => 'image', 'label' => 'Image d\'attente de la vidéo', 'default' => ''],
        ],
    ],

    /* ================================================================
     * ACCUEIL — SECTIONS
     * ================================================================ */
    'home' => [
        'label'  => 'Accueil — Sections',
        'icon'   => 'grid',
        'fields' => [
            'home_trust_title'       => ['type' => 'text', 'label' => 'Bandeau références — titre', 'default' => 'Ils nous ont fait confiance'],
            'home_show_trust'        => ['type' => 'bool', 'label' => 'Afficher le bandeau références', 'default' => '1'],
            'home_expertises_eyebrow'=> ['type' => 'text', 'label' => 'Expertises — sur-titre', 'default' => 'Nos 3 expertises'],
            'home_expertises_title'  => ['type' => 'text', 'label' => 'Expertises — titre', 'default' => 'Une équipe, trois savoir-faire complémentaires.'],
            'home_expertises_text'   => ['type' => 'textarea', 'label' => 'Expertises — texte', 'default' => 'Du site vitrine à la plateforme métier, jusqu\'à l\'exploitation de vos données : NEOSEN vous accompagne sur l\'ensemble de votre transformation digitale.'],
            'home_projects_eyebrow'  => ['type' => 'text', 'label' => 'Réalisations — sur-titre', 'default' => 'Réalisations'],
            'home_projects_title'    => ['type' => 'text', 'label' => 'Réalisations — titre', 'default' => 'Des projets concrets, des solutions qui fonctionnent.'],
            'home_projects_text'     => ['type' => 'textarea', 'label' => 'Réalisations — sous-titre', 'default' => 'Découvrez quelques projets conçus et développés par NEOSEN.'],
            'home_projects_limit'    => ['type' => 'text', 'label' => 'Nombre de réalisations affichées sur l\'accueil', 'default' => '5', 'help' => 'Les deux premières sont affichées en grand format (5 = mise en page idéale).'],
            'home_approach_eyebrow'  => ['type' => 'text', 'label' => 'Approche — sur-titre', 'default' => 'Notre approche'],
            'home_approach_title'    => ['type' => 'text', 'label' => 'Approche — titre', 'default' => 'Une méthode claire, de l\'idée à l\'évolution.'],
            'home_approach_text'     => ['type' => 'textarea', 'label' => 'Approche — texte', 'default' => 'Chaque projet suit un parcours structuré pour garantir qualité, visibilité et respect des délais.'],
            'home_data_eyebrow'      => ['type' => 'text', 'label' => 'Bloc Data — sur-titre', 'default' => 'Data & Business Intelligence'],
            'home_data_title'        => ['type' => 'text', 'label' => 'Bloc Data — titre', 'default' => 'Vos données sont une ressource. Nous vous aidons à en tirer de la valeur.'],
            'home_data_text'         => ['type' => 'textarea', 'label' => 'Bloc Data — texte', 'default' => 'Intégration, modélisation et restitution : nous construisons la chaîne complète qui transforme vos données brutes en indicateurs fiables pour décider.'],
            'home_data_cta'          => ['type' => 'text', 'label' => 'Bloc Data — bouton', 'default' => 'Découvrir l\'expertise Data'],
            'home_why_eyebrow'       => ['type' => 'text', 'label' => 'Pourquoi NEOSEN — sur-titre', 'default' => 'Pourquoi NEOSEN ?'],
            'home_why_title'         => ['type' => 'text', 'label' => 'Pourquoi NEOSEN — titre', 'default' => 'Un partenaire technique qui pense business.'],
            'home_why_text'          => ['type' => 'textarea', 'label' => 'Pourquoi NEOSEN — texte', 'default' => 'Nous ne livrons pas seulement du code : nous construisons des solutions qui répondent à de vrais enjeux métier.'],
            'home_tech_eyebrow'      => ['type' => 'text', 'label' => 'Technologies — sur-titre', 'default' => 'Technologies'],
            'home_tech_title'        => ['type' => 'text', 'label' => 'Technologies — titre', 'default' => 'Des technologies éprouvées, choisies pour durer.'],
            'home_tech_text'         => ['type' => 'textarea', 'label' => 'Technologies — texte', 'default' => 'Nous sélectionnons les outils les plus adaptés à chaque projet, avec un objectif : performance, sécurité et maintenabilité.'],
            'home_show_stats'        => ['type' => 'bool', 'label' => 'Afficher les chiffres clés', 'default' => '1'],
        ],
    ],

    /* ================================================================
     * APPEL À L'ACTION
     * ================================================================ */
    'cta' => [
        'label'  => 'Appel à l\'action',
        'icon'   => 'target',
        'fields' => [
            'cta_eyebrow'        => ['type' => 'text', 'label' => 'Sur-titre', 'default' => 'Parlons de votre projet'],
            'cta_title'          => ['type' => 'text', 'label' => 'Titre', 'default' => 'Vous avez une idée, un besoin ou un projet ?'],
            'cta_text'           => ['type' => 'textarea', 'label' => 'Texte', 'default' => 'Parlons de votre projet et construisons ensemble une solution adaptée à vos objectifs.'],
            'cta_quote_label'    => ['type' => 'text', 'label' => 'Bouton « devis »', 'default' => 'Demander un devis'],
            'cta_contact_label'  => ['type' => 'text', 'label' => 'Bouton « contact »', 'default' => 'Nous contacter'],
            'cta_whatsapp_label' => ['type' => 'text', 'label' => 'Bouton WhatsApp', 'default' => 'WhatsApp'],
            'header_cta_label'   => ['type' => 'text', 'label' => 'Bouton du menu', 'default' => 'Parlons de votre projet'],
        ],
    ],

    /* ================================================================
     * PAGE À PROPOS
     * ================================================================ */
    'about' => [
        'label'  => 'Page À propos',
        'icon'   => 'users',
        'fields' => [
            'about_eyebrow'  => ['type' => 'text', 'label' => 'Sur-titre', 'default' => 'À propos'],
            'about_title'    => ['type' => 'text', 'label' => 'Titre', 'default' => 'Un studio technologique au service de vos ambitions.'],
            'about_intro'    => ['type' => 'textarea', 'label' => 'Introduction', 'default' => 'NEOSEN accompagne les entreprises depuis une simple idée jusqu\'à une solution digitale complète : site web, application, logiciel métier ou projet Data.'],
            'about_body'     => ['type' => 'html', 'label' => 'Texte de présentation', 'default' => "<p>NEOSEN est une entreprise technologique spécialisée dans trois domaines complémentaires : la <strong>création de sites web</strong>, le <strong>développement de logiciels et d'applications</strong>, et la <strong>Data &amp; Business Intelligence</strong>.</p>\n<p>Cette double compétence — développement digital et ingénierie des données — nous permet de concevoir des solutions complètes : des outils qui ne se contentent pas de fonctionner, mais qui produisent aussi les informations nécessaires au pilotage de votre activité.</p>\n<p>Notre conviction : un bon projet numérique part toujours d'un besoin métier clair. C'est pourquoi nous commençons chaque collaboration par l'écoute, avant de concevoir, développer, tester et faire évoluer la solution avec vous.</p>"],
            'about_image'    => ['type' => 'image', 'label' => 'Image', 'default' => ''],
            'about_video'    => ['type' => 'video', 'label' => 'Vidéo de présentation (optionnelle)', 'default' => ''],
            'about_mission_title' => ['type' => 'text', 'label' => 'Mission — titre', 'default' => 'Notre mission'],
            'about_mission'  => ['type' => 'textarea', 'label' => 'Mission — texte', 'default' => 'Rendre la technologie utile et accessible aux entreprises, en concevant des solutions numériques fiables, évolutives et orientées résultats.'],
            'about_vision_title' => ['type' => 'text', 'label' => 'Vision — titre', 'default' => 'Notre vision'],
            'about_vision'   => ['type' => 'textarea', 'label' => 'Vision — texte', 'default' => 'Devenir le partenaire de référence des entreprises qui veulent digitaliser leurs processus et piloter leur activité par la donnée.'],
            'about_values_title' => ['type' => 'text', 'label' => 'Valeurs — titre de section', 'default' => 'Ce qui nous guide'],
        ],
    ],

    /* ================================================================
     * PAGES SERVICES & RÉALISATIONS
     * ================================================================ */
    'pages' => [
        'label'  => 'Services & Réalisations',
        'icon'   => 'code',
        'fields' => [
            'services_eyebrow' => ['type' => 'text', 'label' => 'Services — sur-titre', 'default' => 'Services'],
            'services_title'   => ['type' => 'text', 'label' => 'Services — titre', 'default' => 'Web, logiciels et data : une expertise de bout en bout.'],
            'services_intro'   => ['type' => 'textarea', 'label' => 'Services — introduction', 'default' => 'Nous intervenons aussi bien sur le développement digital que sur les projets Data / BI, pour vous proposer des solutions complètes et cohérentes.'],
            'projects_eyebrow' => ['type' => 'text', 'label' => 'Réalisations — sur-titre', 'default' => 'Réalisations'],
            'projects_title'   => ['type' => 'text', 'label' => 'Réalisations — titre', 'default' => 'Des projets concrets, des solutions qui fonctionnent.'],
            'projects_intro'   => ['type' => 'textarea', 'label' => 'Réalisations — introduction', 'default' => 'Plateformes SaaS, sites institutionnels, solutions FinTech ou logistiques : découvrez des projets conçus et développés par NEOSEN.'],
        ],
    ],

    /* ================================================================
     * PAGE DATA / BI
     * ================================================================ */
    'data' => [
        'label'  => 'Page Data / BI',
        'icon'   => 'chart',
        'fields' => [
            'data_eyebrow'    => ['type' => 'text', 'label' => 'Sur-titre', 'default' => 'Data & Business Intelligence'],
            'data_title'      => ['type' => 'text', 'label' => 'Titre', 'default' => 'Vos données sont une ressource. Nous vous aidons à en tirer de la valeur.'],
            'data_intro'      => ['type' => 'textarea', 'label' => 'Présentation', 'default' => 'NEOSEN accompagne les entreprises dans la conception et la mise en place de solutions d\'intégration, de modélisation et de restitution des données.'],
            'data_image'      => ['type' => 'image', 'label' => 'Image (optionnelle, remplace l\'illustration)', 'default' => ''],
            'data_offers_title' => ['type' => 'text', 'label' => 'Prestations — titre', 'default' => 'Nos prestations Data'],
            'data_offers_text'  => ['type' => 'textarea', 'label' => 'Prestations — texte', 'default' => 'Une expertise orientée Microsoft BI et ingénierie des données, de la source jusqu\'au tableau de bord.'],
            'data_pipeline_title' => ['type' => 'text', 'label' => 'Chaîne de valeur — titre', 'default' => 'De la donnée brute à la décision'],
            'data_pipeline'   => ['type' => 'lines', 'label' => 'Chaîne de valeur — étapes', 'default' => "Sources|ERP, CRM, fichiers, API\nIntégration|ETL / ELT, SSIS\nEntrepôt|Data Warehouse, modèle en étoile\nRestitution|Power BI, KPI, reporting", 'help' => 'Une étape par ligne au format : Titre|Description'],
            'data_stack_title' => ['type' => 'text', 'label' => 'Technologies Data — titre', 'default' => 'Notre stack Data'],
            'data_cta_title'  => ['type' => 'text', 'label' => 'CTA — titre', 'default' => 'Un projet Data ou BI ?'],
            'data_cta_text'   => ['type' => 'textarea', 'label' => 'CTA — texte', 'default' => 'Audit de l\'existant, mise en place d\'un entrepôt de données, optimisation SQL ou tableaux de bord Power BI : parlons de vos enjeux.'],
            'data_cta_label'  => ['type' => 'text', 'label' => 'CTA — bouton', 'default' => 'Parler de votre projet Data'],
        ],
    ],

    /* ================================================================
     * PAGE CONTACT
     * ================================================================ */
    'contact_page' => [
        'label'  => 'Page Contact',
        'icon'   => 'mail',
        'fields' => [
            'contact_eyebrow'   => ['type' => 'text', 'label' => 'Sur-titre', 'default' => 'Contact'],
            'contact_title'     => ['type' => 'text', 'label' => 'Titre', 'default' => 'Parlons de votre projet.'],
            'contact_intro'     => ['type' => 'textarea', 'label' => 'Introduction', 'default' => 'Décrivez-nous votre besoin : nous revenons vers vous sous 48 h ouvrées avec une première analyse et les prochaines étapes.'],
            'contact_success'   => ['type' => 'textarea', 'label' => 'Message de confirmation', 'default' => 'Merci ! Votre demande a bien été envoyée. Notre équipe vous répondra dans les plus brefs délais.'],
            'contact_project_types' => ['type' => 'lines', 'label' => 'Types de projet (liste déroulante)', 'default' => "Site Web\nE-commerce\nApplication mobile\nLogiciel\nPlateforme Web\nProjet Data / BI\nMaintenance\nAutre"],
            'contact_budgets'   => ['type' => 'lines', 'label' => 'Budgets indicatifs (liste déroulante)', 'default' => "Moins de 1 000 000 FCFA\n1 000 000 – 3 000 000 FCFA\n3 000 000 – 7 000 000 FCFA\nPlus de 7 000 000 FCFA\nÀ définir ensemble"],
            'contact_steps'     => ['type' => 'lines', 'label' => 'Étapes après envoi', 'default' => "Nous étudions votre demande\nNous vous recontactons sous 48 h\nNous vous proposons une solution et un devis"],
            'mail_subject'      => ['type' => 'text', 'label' => 'Objet de l\'email de notification', 'default' => 'Nouvelle demande de projet — {name}'],
            'mail_autoreply'    => ['type' => 'bool', 'label' => 'Envoyer un accusé de réception au visiteur', 'default' => '1'],
            'mail_autoreply_text' => ['type' => 'textarea', 'label' => 'Texte de l\'accusé de réception', 'default' => "Bonjour {first_name},\n\nNous avons bien reçu votre demande et vous remercions de votre confiance. Notre équipe l'étudie et revient vers vous très rapidement.\n\nÀ très bientôt,\nL'équipe {site}"],
        ],
    ],

    /* ================================================================
     * SEO
     * ================================================================ */
    'seo' => [
        'label'  => 'SEO',
        'icon'   => 'search',
        'fields' => [
            'seo_home_title'     => ['type' => 'text', 'label' => 'Accueil — balise title', 'default' => 'NEOSEN — Création de sites web, applications & Data / BI'],
            'seo_home_desc'      => ['type' => 'textarea', 'label' => 'Accueil — meta description', 'default' => 'NEOSEN conçoit des sites web, développe des applications et logiciels sur mesure, et accompagne les entreprises dans leurs projets Data & Business Intelligence.'],
            'seo_about_title'    => ['type' => 'text', 'label' => 'À propos — title', 'default' => 'À propos de NEOSEN — Studio technologique'],
            'seo_about_desc'     => ['type' => 'textarea', 'label' => 'À propos — description', 'default' => 'Découvrez NEOSEN, studio technologique spécialisé en développement web, logiciels, applications et Data / BI.'],
            'seo_services_title' => ['type' => 'text', 'label' => 'Services — title', 'default' => 'Services — Sites web, logiciels & Data | NEOSEN'],
            'seo_services_desc'  => ['type' => 'textarea', 'label' => 'Services — description', 'default' => 'Création de sites web, développement d\'applications et logiciels sur mesure, intégration de données et Power BI.'],
            'seo_projects_title' => ['type' => 'text', 'label' => 'Réalisations — title', 'default' => 'Réalisations — Projets web, SaaS & plateformes | NEOSEN'],
            'seo_projects_desc'  => ['type' => 'textarea', 'label' => 'Réalisations — description', 'default' => 'Découvrez les projets conçus et développés par NEOSEN : plateformes SaaS, sites institutionnels, FinTech, logistique.'],
            'seo_data_title'     => ['type' => 'text', 'label' => 'Data — title', 'default' => 'Data & Business Intelligence — SQL Server, SSIS, Power BI | NEOSEN'],
            'seo_data_desc'      => ['type' => 'textarea', 'label' => 'Data — description', 'default' => 'Intégration de données, ETL/ELT, SSIS, Data Warehouse, modélisation, optimisation SQL Server et reporting Power BI.'],
            'seo_contact_title'  => ['type' => 'text', 'label' => 'Contact — title', 'default' => 'Contact — Parlons de votre projet | NEOSEN'],
            'seo_contact_desc'   => ['type' => 'textarea', 'label' => 'Contact — description', 'default' => 'Demandez un devis ou parlez-nous de votre projet web, application, logiciel ou Data / BI.'],
            'seo_indexing'       => ['type' => 'bool', 'label' => 'Autoriser l\'indexation par les moteurs de recherche', 'default' => '1'],
            'analytics_id'       => ['type' => 'text', 'label' => 'ID Google Analytics 4 (optionnel)', 'default' => '', 'help' => 'Ex : G-XXXXXXXXXX'],
        ],
    ],

    /* ================================================================
     * MENTIONS LÉGALES
     * ================================================================ */
    'legal' => [
        'label'  => 'Pages légales',
        'icon'   => 'shield',
        'fields' => [
            'legal_mentions' => ['type' => 'html', 'label' => 'Mentions légales', 'default' => "<h2>Éditeur du site</h2>\n<p>Le présent site est édité par NEOSEN.<br>Siège : Dakar, Sénégal.<br>Contact : contact@neosen.sn</p>\n<h2>Hébergement</h2>\n<p>À compléter avec les coordonnées de l'hébergeur.</p>\n<h2>Propriété intellectuelle</h2>\n<p>L'ensemble des contenus de ce site (textes, images, logos) est la propriété de NEOSEN ou de ses clients, et ne peut être reproduit sans autorisation.</p>\n<h2>Réalisations</h2>\n<p>Les marques et visuels des projets présentés appartiennent à leurs propriétaires respectifs.</p>"],
            'privacy_policy' => ['type' => 'html', 'label' => 'Politique de confidentialité', 'default' => "<h2>Données collectées</h2>\n<p>Lorsque vous utilisez le formulaire de contact, nous collectons les informations que vous nous transmettez (nom, prénom, entreprise, email, téléphone, message et pièce jointe éventuelle).</p>\n<h2>Finalité</h2>\n<p>Ces données sont utilisées uniquement pour répondre à votre demande et assurer le suivi de la relation commerciale. Elles ne sont jamais cédées à des tiers.</p>\n<h2>Durée de conservation</h2>\n<p>Les données sont conservées au maximum 3 ans après le dernier contact.</p>\n<h2>Vos droits</h2>\n<p>Vous disposez d'un droit d'accès, de rectification et de suppression de vos données. Pour l'exercer, écrivez-nous à contact@neosen.sn.</p>\n<h2>Protection anti-spam</h2>\n<p>Le formulaire est protégé par Google reCAPTCHA, soumis aux règles de confidentialité de Google.</p>"],
        ],
    ],
];
