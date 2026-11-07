CREATE TABLE IF NOT EXISTS scan_schedules (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  created_by BIGINT UNSIGNED NOT NULL,
  frequency ENUM('daily', 'weekly') NOT NULL,
  status ENUM('Active', 'Paused', 'Expired') NOT NULL DEFAULT 'Active',
  next_run_at DATETIME NOT NULL,
  expires_at DATETIME NOT NULL,
  authorization_confirmed_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_scan_schedules_org_asset (organization_id, asset_id),
  UNIQUE KEY uq_scan_schedules_org_id (organization_id, id),
  KEY ix_scan_schedules_due (status, next_run_at, expires_at),
  KEY ix_scan_schedules_expiry (status, expires_at),
  CONSTRAINT fk_scan_schedules_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_scan_schedules_asset FOREIGN KEY (organization_id, asset_id)
    REFERENCES assets (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_scan_schedules_creator FOREIGN KEY (organization_id, created_by)
    REFERENCES users (organization_id, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS scan_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  organization_id BIGINT UNSIGNED NOT NULL,
  asset_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  schedule_id BIGINT UNSIGNED NULL,
  status ENUM('Queued', 'Running', 'Completed', 'Failed', 'Cancelled') NOT NULL DEFAULT 'Queued',
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME NULL DEFAULT NULL,
  completed_at DATETIME NULL DEFAULT NULL,
  lease_expires_at DATETIME NULL DEFAULT NULL,
  error_message VARCHAR(500) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  KEY ix_scan_jobs_queue (status, available_at, id),
  KEY ix_scan_jobs_org_asset (organization_id, asset_id, id),
  KEY ix_scan_jobs_lease (status, lease_expires_at, attempts),
  KEY ix_scan_jobs_schedule (schedule_id, status),
  CONSTRAINT fk_scan_jobs_organization FOREIGN KEY (organization_id)
    REFERENCES organizations (id) ON DELETE CASCADE,
  CONSTRAINT fk_scan_jobs_asset FOREIGN KEY (organization_id, asset_id)
    REFERENCES assets (organization_id, id) ON DELETE CASCADE,
  CONSTRAINT fk_scan_jobs_requester FOREIGN KEY (organization_id, requested_by)
    REFERENCES users (organization_id, id),
  CONSTRAINT fk_scan_jobs_schedule FOREIGN KEY (schedule_id)
    REFERENCES scan_schedules (id) ON DELETE SET NULL
) ENGINE=InnoDB;
