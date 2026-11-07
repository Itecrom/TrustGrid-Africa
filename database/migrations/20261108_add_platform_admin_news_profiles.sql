ALTER TABLE organizations
  MODIFY status ENUM('pending', 'active', 'suspended', 'rejected') NOT NULL DEFAULT 'pending';

ALTER TABLE users
  MODIFY role ENUM('super_admin', 'admin', 'analyst', 'contributor', 'viewer') NOT NULL DEFAULT 'viewer';

CREATE TABLE IF NOT EXISTS user_profiles (
  user_id BIGINT UNSIGNED NOT NULL,
  bio TEXT NOT NULL,
  avatar_path VARCHAR(255) NOT NULL DEFAULT '',
  school VARCHAR(180) NOT NULL DEFAULT '',
  work VARCHAR(180) NOT NULL DEFAULT '',
  qualifications TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_user_profiles_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS news_articles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  author_user_id BIGINT UNSIGNED NOT NULL,
  slug VARCHAR(180) NOT NULL,
  title VARCHAR(180) NOT NULL,
  summary VARCHAR(500) NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  featured_image_path VARCHAR(255) NOT NULL DEFAULT '',
  status ENUM('Draft', 'Published', 'Archived') NOT NULL DEFAULT 'Draft',
  published_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_news_articles_slug (slug),
  KEY ix_news_articles_status_published (status, published_at, id),
  CONSTRAINT fk_news_articles_author FOREIGN KEY (author_user_id)
    REFERENCES users (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS news_comments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  article_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  display_name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  body_text TEXT NOT NULL,
  status ENUM('Pending', 'Approved', 'Rejected') NOT NULL DEFAULT 'Pending',
  moderated_by BIGINT UNSIGNED NULL,
  moderated_at DATETIME NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_news_comments_article_status (article_id, status, created_at),
  KEY ix_news_comments_status_created (status, created_at),
  CONSTRAINT fk_news_comments_article FOREIGN KEY (article_id)
    REFERENCES news_articles (id) ON DELETE CASCADE,
  CONSTRAINT fk_news_comments_user FOREIGN KEY (user_id)
    REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT fk_news_comments_moderator FOREIGN KEY (moderated_by)
    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_action_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NULL,
  actor_user_id BIGINT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  target_type VARCHAR(48) NOT NULL,
  target_id BIGINT UNSIGNED NULL,
  details_json JSON NULL,
  ip_hash CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_user_action_logs_org_created (organization_id, created_at, id),
  KEY ix_user_action_logs_created (created_at, id),
  KEY ix_user_action_logs_actor_created (actor_user_id, created_at),
  CONSTRAINT fk_user_action_logs_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE SET NULL,
  CONSTRAINT fk_user_action_logs_actor FOREIGN KEY (actor_user_id)
    REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS public_form_rate_limits (
  purpose VARCHAR(32) NOT NULL,
  ip_hash CHAR(64) NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (purpose, ip_hash)
) ENGINE=InnoDB;
