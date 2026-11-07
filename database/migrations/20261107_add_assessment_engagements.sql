CREATE TABLE IF NOT EXISTS assessment_engagements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  title VARCHAR(180) NOT NULL,
  scope_text TEXT NOT NULL,
  exclusions TEXT NOT NULL,
  rules_of_engagement TEXT NOT NULL,
  lead_name VARCHAR(120) NOT NULL,
  authorization_note VARCHAR(500) NOT NULL,
  authorization_confirmed TINYINT(1) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('Planned', 'Active', 'Completed', 'Cancelled') NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assessment_engagements_org_id (organization_id, id),
  KEY ix_assessment_engagements_org_status (organization_id, status, start_date, end_date),
  CONSTRAINT fk_assessment_engagements_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_assessment_engagements_asset FOREIGN KEY (organization_id, asset_id)
    REFERENCES assets (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_assessment_engagements_creator FOREIGN KEY (organization_id, created_by)
    REFERENCES users (organization_id, id)
) ENGINE=InnoDB;

ALTER TABLE guided_assessments
  ADD COLUMN engagement_id BIGINT UNSIGNED NULL AFTER asset_id,
  ADD KEY ix_guided_assessments_engagement (organization_id, engagement_id),
  ADD CONSTRAINT fk_guided_assessments_engagement FOREIGN KEY (organization_id, engagement_id)
    REFERENCES assessment_engagements (organization_id, id) ON DELETE CASCADE;

ALTER TABLE guided_assessments
  MODIFY status ENUM('Draft', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Draft';
