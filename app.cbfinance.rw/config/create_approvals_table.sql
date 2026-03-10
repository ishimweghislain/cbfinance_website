-- ============================================================
-- CB Finance: Approval Workflow Table
-- Run this once in your MySQL/phpMyAdmin
-- ============================================================

CREATE TABLE IF NOT EXISTS `pending_approvals` (
  `approval_id`       INT          NOT NULL AUTO_INCREMENT,
  `action_type`       VARCHAR(20)  NOT NULL COMMENT 'add | edit | delete',
  `entity_type`       VARCHAR(30)  NOT NULL COMMENT 'customer | loan',
  `entity_id`         INT          NULL      COMMENT 'ID of existing record (for edit/delete)',
  `action_data`       LONGTEXT     NOT NULL  COMMENT 'JSON snapshot of form data',
  `description`       VARCHAR(255) NOT NULL  COMMENT 'Human-readable summary',
  `submitted_by`      VARCHAR(100) NOT NULL,
  `submitted_by_role` VARCHAR(50)  NOT NULL,
  `status`            ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by`       VARCHAR(100) NULL,
  `reviewed_at`       DATETIME     NULL,
  `review_notes`      TEXT         NULL,
  `submitted_at`      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`approval_id`),
  KEY `idx_status`     (`status`),
  KEY `idx_entity`     (`entity_type`, `entity_id`),
  KEY `idx_submitted`  (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Pending approval queue for sensitive system actions';
