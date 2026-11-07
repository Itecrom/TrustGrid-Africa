CREATE TABLE IF NOT EXISTS organizations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(160) NOT NULL,
  country CHAR(2) NOT NULL,
  status ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(254) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'analyst', 'contributor', 'viewer') NOT NULL DEFAULT 'viewer',
  status ENUM('active', 'disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  UNIQUE KEY uq_users_org_id (organization_id, id),
  CONSTRAINT fk_users_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(160) NOT NULL,
  type ENUM('Website', 'Web application', 'API', 'Domain', 'Database', 'Server') NOT NULL,
  target VARCHAR(2048) NOT NULL,
  owner_team VARCHAR(120) NOT NULL DEFAULT '',
  status ENUM('Active', 'Monitoring', 'Needs review', 'Archived') NOT NULL DEFAULT 'Active',
  risk_level ENUM('Unassessed', 'Low', 'Moderate', 'High', 'Critical') NOT NULL DEFAULT 'Unassessed',
  authorization_confirmed TINYINT(1) NOT NULL DEFAULT 0,
  authorization_note VARCHAR(500) NOT NULL,
  authorized_by BIGINT UNSIGNED NOT NULL,
  authorized_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assets_org_id (organization_id, id),
  KEY ix_assets_org_status (organization_id, status),
  CONSTRAINT fk_assets_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_assets_authorized_by FOREIGN KEY (organization_id, authorized_by)
    REFERENCES users (organization_id, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS assessments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  assessor_id BIGINT UNSIGNED NOT NULL,
  assessment_type ENUM('manual_review', 'safe_web_review') NOT NULL DEFAULT 'manual_review',
  summary VARCHAR(500) NOT NULL,
  risk_score TINYINT UNSIGNED NOT NULL,
  completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assessments_org_id (organization_id, id),
  KEY ix_assessments_org_completed (organization_id, completed_at),
  CONSTRAINT fk_assessments_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_assessments_asset FOREIGN KEY (organization_id, asset_id)
    REFERENCES assets (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_assessments_assessor FOREIGN KEY (organization_id, assessor_id)
    REFERENCES users (organization_id, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS guided_assessments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  scope TEXT NOT NULL,
  frameworks_json JSON NOT NULL,
  status ENUM('Draft', 'Completed') NOT NULL DEFAULT 'Draft',
  overall_score TINYINT UNSIGNED NULL,
  completed_assessment_id BIGINT UNSIGNED NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_guided_assessments_org_id (organization_id, id),
  UNIQUE KEY uq_guided_assessments_base_assessment (organization_id, completed_assessment_id),
  KEY ix_guided_assessments_org_status (organization_id, status, updated_at),
  CONSTRAINT fk_guided_assessments_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_guided_assessments_asset FOREIGN KEY (organization_id, asset_id)
    REFERENCES assets (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_guided_assessments_base_assessment FOREIGN KEY (organization_id, completed_assessment_id)
    REFERENCES assessments (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_guided_assessments_creator FOREIGN KEY (organization_id, created_by)
    REFERENCES users (organization_id, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS guided_assessment_answers (
  organization_id BIGINT UNSIGNED NOT NULL,
  guided_assessment_id BIGINT UNSIGNED NOT NULL,
  control_id VARCHAR(64) NOT NULL,
  answer ENUM('implemented', 'partial', 'not_implemented', 'not_sure', 'not_applicable') NOT NULL,
  notes TEXT NOT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (organization_id, guided_assessment_id, control_id),
  CONSTRAINT fk_guided_assessment_answers_assessment FOREIGN KEY (organization_id, guided_assessment_id)
    REFERENCES guided_assessments (organization_id, id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS web_scans (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  assessment_id BIGINT UNSIGNED NOT NULL,
  scanner_id BIGINT UNSIGNED NOT NULL,
  checks_json JSON NOT NULL,
  completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_web_scans_assessment (organization_id, assessment_id),
  KEY ix_web_scans_asset_completed (organization_id, asset_id, completed_at),
  CONSTRAINT fk_web_scans_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_web_scans_asset FOREIGN KEY (organization_id, asset_id)
    REFERENCES assets (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_web_scans_assessment FOREIGN KEY (organization_id, assessment_id)
    REFERENCES assessments (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_web_scans_scanner FOREIGN KEY (organization_id, scanner_id)
    REFERENCES users (organization_id, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS asset_scan_limits (
  organization_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  last_requested_at TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (organization_id, asset_id),
  CONSTRAINT fk_asset_scan_limits_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_asset_scan_limits_asset FOREIGN KEY (organization_id, asset_id)
    REFERENCES assets (organization_id, id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS findings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  assessment_id BIGINT UNSIGNED NULL,
  title VARCHAR(180) NOT NULL,
  description TEXT NOT NULL,
  evidence TEXT NULL,
  recommendation TEXT NOT NULL,
  severity ENUM('Low', 'Medium', 'High', 'Critical') NOT NULL,
  status ENUM('Open', 'In progress', 'Resolved') NOT NULL DEFAULT 'Open',
  created_by BIGINT UNSIGNED NOT NULL,
  resolved_at TIMESTAMP NULL DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_findings_org_status (organization_id, status),
  KEY ix_findings_asset (asset_id),
  CONSTRAINT fk_findings_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_findings_asset FOREIGN KEY (organization_id, asset_id)
    REFERENCES assets (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_findings_assessment FOREIGN KEY (organization_id, assessment_id)
    REFERENCES assessments (organization_id, id),
  CONSTRAINT fk_findings_creator FOREIGN KEY (organization_id, created_by)
    REFERENCES users (organization_id, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  ip_hash CHAR(64) NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ip_hash)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS signup_rate_limits (
  ip_hash CHAR(64) NOT NULL,
  attempts SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  window_started_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (ip_hash)
) ENGINE=InnoDB;
