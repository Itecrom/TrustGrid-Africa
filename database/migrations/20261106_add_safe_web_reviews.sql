ALTER TABLE assessments
  MODIFY assessment_type ENUM('manual_review', 'safe_web_review')
  NOT NULL DEFAULT 'manual_review';

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
