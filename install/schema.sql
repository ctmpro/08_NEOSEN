-- ============================================================
-- NEOSEN — Schéma de base de données (MySQL 8.x / MariaDB 10.5+)
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Administrateurs
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(120) NOT NULL,
    email           VARCHAR(190) NOT NULL,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('superadmin','admin','editor') NOT NULL DEFAULT 'admin',
    is_active       TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at   DATETIME NULL,
    last_login_ip   VARCHAR(45) NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address      VARCHAR(45) NOT NULL,
    email           VARCHAR(190) NOT NULL DEFAULT '',
    success         TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_attempts_ip (ip_address, attempted_at),
    KEY idx_attempts_email (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Paramètres / contenus éditables (clé-valeur, multilingue)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
    setting_key     VARCHAR(100) NOT NULL,
    lang            VARCHAR(5) NOT NULL DEFAULT 'fr',
    setting_value   MEDIUMTEXT NULL,
    updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_key, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Services (les 3 expertises et plus si besoin)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS services (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lang                VARCHAR(5) NOT NULL DEFAULT 'fr',
    name                VARCHAR(150) NOT NULL,
    slug                VARCHAR(160) NOT NULL,
    eyebrow             VARCHAR(80) NULL,
    short_description   TEXT NULL,
    description         MEDIUMTEXT NULL,
    icon                VARCHAR(60) NULL,
    image               VARCHAR(255) NULL,
    items               TEXT NULL COMMENT 'Prestations, une par ligne',
    cta_label           VARCHAR(80) NULL,
    link_url            VARCHAR(255) NULL COMMENT 'Lien alternatif du CTA (optionnel)',
    seo_title           VARCHAR(190) NULL,
    seo_description     VARCHAR(300) NULL,
    sort_order          INT NOT NULL DEFAULT 0,
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_services_slug (slug, lang),
    KEY idx_services_order (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Catégories de réalisations (filtres)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS project_categories (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lang        VARCHAR(5) NOT NULL DEFAULT 'fr',
    name        VARCHAR(80) NOT NULL,
    slug        VARCHAR(90) NOT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    UNIQUE KEY uq_categories_slug (slug, lang)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Réalisations
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS projects (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lang                VARCHAR(5) NOT NULL DEFAULT 'fr',
    name                VARCHAR(150) NOT NULL,
    slug                VARCHAR(160) NOT NULL,
    category_id         INT UNSIGNED NULL,
    category_label      VARCHAR(120) NULL COMMENT 'Libellé affiché (ex : Logiciel / SaaS / SIRH)',
    client              VARCHAR(150) NULL,
    url                 VARCHAR(255) NULL,
    year                SMALLINT UNSIGNED NULL,
    short_description   TEXT NULL,
    long_description    MEDIUMTEXT NULL,
    need                MEDIUMTEXT NULL COMMENT 'Le besoin',
    solution            MEDIUMTEXT NULL COMMENT 'Notre solution',
    features            TEXT NULL COMMENT 'Fonctionnalités, une par ligne',
    results             MEDIUMTEXT NULL COMMENT 'Résultat',
    technologies        VARCHAR(500) NULL COMMENT 'Séparées par des virgules',
    main_image          VARCHAR(255) NULL,
    is_featured         TINYINT(1) NOT NULL DEFAULT 0,
    is_visible          TINYINT(1) NOT NULL DEFAULT 1,
    status              ENUM('published','draft') NOT NULL DEFAULT 'draft',
    seo_title           VARCHAR(190) NULL,
    seo_description     VARCHAR(300) NULL,
    sort_order          INT NOT NULL DEFAULT 0,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_projects_slug (slug, lang),
    KEY idx_projects_public (status, is_visible, sort_order),
    KEY idx_projects_category (category_id),
    CONSTRAINT fk_projects_category FOREIGN KEY (category_id)
        REFERENCES project_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS project_images (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id  INT UNSIGNED NOT NULL,
    path        VARCHAR(255) NOT NULL,
    alt         VARCHAR(255) NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_images_project (project_id, sort_order),
    CONSTRAINT fk_images_project FOREIGN KEY (project_id)
        REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Blocs de contenu génériques
-- (méthodologie, arguments, prestations data, valeurs, chiffres…)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS content_blocks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    lang        VARCHAR(5) NOT NULL DEFAULT 'fr',
    section     VARCHAR(50) NOT NULL,
    title       VARCHAR(190) NOT NULL,
    subtitle    VARCHAR(190) NULL,
    description TEXT NULL,
    icon        VARCHAR(60) NULL,
    image       VARCHAR(255) NULL,
    items       TEXT NULL COMMENT 'Liste, une entrée par ligne',
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_blocks_section (lang, section, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Technologies
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS technologies (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(80) NOT NULL,
    category    VARCHAR(60) NOT NULL DEFAULT 'Web',
    icon        VARCHAR(255) NULL COMMENT 'Image (optionnelle)',
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_tech_category (is_active, category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Demandes de contact
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    company         VARCHAR(150) NULL,
    email           VARCHAR(190) NOT NULL,
    phone           VARCHAR(40) NULL,
    project_type    VARCHAR(100) NULL,
    budget          VARCHAR(100) NULL,
    message         TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_name VARCHAR(255) NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      VARCHAR(255) NULL,
    is_read         TINYINT(1) NOT NULL DEFAULT 0,
    is_archived     TINYINT(1) NOT NULL DEFAULT 0,
    mail_sent       TINYINT(1) NOT NULL DEFAULT 0,
    admin_notes     TEXT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_messages_read (is_read, created_at),
    KEY idx_messages_ip (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
