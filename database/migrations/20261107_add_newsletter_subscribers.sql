CREATE TABLE IF NOT EXISTS news_subscribers (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(254) NOT NULL,
  name VARCHAR(120) NOT NULL DEFAULT '',
  status ENUM('Active', 'Unsubscribed') NOT NULL DEFAULT 'Active',
  consent_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at DATETIME NULL DEFAULT NULL,
  ip_hash CHAR(64) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_news_subscribers_email (email),
  KEY ix_news_subscribers_status_created (status, created_at, id)
) ENGINE=InnoDB;
