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
