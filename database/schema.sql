-- TimeInOut — multi-tenant check-in SaaS
-- MySQL 5.7+ / MariaDB 10.3+. Import once (phpMyAdmin → Import, or: mysql dbname < schema.sql)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS tenants (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name            VARCHAR(120) NOT NULL,
  slug            VARCHAR(40)  NOT NULL,
  custom_domain   VARCHAR(190) NULL,
  plan            VARCHAR(30)  NOT NULL DEFAULT 'starter',
  status          ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'trial',
  trial_ends_at   DATETIME NULL,
  -- branding / home page
  logo_path       VARCHAR(255) NULL,
  primary_color   VARCHAR(7)   NOT NULL DEFAULT '#6C5CE7',
  accent_color    VARCHAR(7)   NOT NULL DEFAULT '#00B894',
  welcome_title   VARCHAR(150) NULL,
  welcome_text    VARCHAR(500) NULL,
  timezone        VARCHAR(64)  NOT NULL DEFAULT 'America/New_York',
  -- kiosk
  kiosk_public    TINYINT(1)   NOT NULL DEFAULT 0,
  allow_past_days TINYINT UNSIGNED NOT NULL DEFAULT 7,
  -- reports
  report_emails   VARCHAR(500) NULL,
  report_daily    TINYINT(1)   NOT NULL DEFAULT 1,
  report_weekly   TINYINT(1)   NOT NULL DEFAULT 1,
  report_monthly  TINYINT(1)   NOT NULL DEFAULT 1,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tenant_slug (slug),
  UNIQUE KEY uq_tenant_domain (custom_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('owner','admin','staff') NOT NULL DEFAULT 'staff',
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_email (tenant_id, email),
  CONSTRAINT fk_users_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  student_code VARCHAR(50)  NULL,
  first_name   VARCHAR(80)  NOT NULL,
  last_name    VARCHAR(80)  NOT NULL DEFAULT '',
  grade        VARCHAR(30)  NULL,
  active       TINYINT(1)   NOT NULL DEFAULT 1,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_code (tenant_id, student_code),
  KEY ix_student_name (tenant_id, first_name, last_name),
  CONSTRAINT fk_students_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Each guardian belongs to exactly one student, so the kiosk only ever shows
-- that student's own parents/guardians.
CREATE TABLE IF NOT EXISTS guardians (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  student_id   INT UNSIGNED NOT NULL,
  name         VARCHAR(120) NOT NULL,
  relationship VARCHAR(40)  NULL,
  phone        VARCHAR(40)  NULL,
  email        VARCHAR(190) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_guardian_student (tenant_id, student_id),
  CONSTRAINT fk_guardians_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_guardians_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS teachers (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  employee_code VARCHAR(50)  NULL,
  first_name    VARCHAR(80)  NOT NULL,
  last_name     VARCHAR(80)  NOT NULL DEFAULT '',
  email         VARCHAR(190) NULL,
  phone         VARCHAR(40)  NULL,
  active        TINYINT(1)   NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_teacher_code (tenant_id, employee_code),
  KEY ix_teacher_name (tenant_id, first_name, last_name),
  CONSTRAINT fk_teachers_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS materials (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id  INT UNSIGNED NOT NULL,
  name       VARCHAR(120) NOT NULL,
  active     TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  CONSTRAINT fk_materials_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every kiosk action is one row. Names are copied in (snapshot) so history
-- stays readable even if a student/guardian is later edited or deleted.
CREATE TABLE IF NOT EXISTS attendance (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id     INT UNSIGNED NOT NULL,
  person_type   ENUM('student','teacher') NOT NULL,
  person_id     INT UNSIGNED NOT NULL,
  person_name   VARCHAR(170) NOT NULL,
  guardian_id   INT UNSIGNED NULL,
  guardian_name VARCHAR(120) NULL,
  kind          ENUM('sign_in','sign_out','material_pickup') NOT NULL,
  event_date    DATE NOT NULL,
  event_time    TIME NOT NULL,
  materials     VARCHAR(500) NULL,
  note          VARCHAR(255) NULL,
  recorded_by   INT UNSIGNED NULL,
  ip            VARCHAR(45) NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_att_date (tenant_id, event_date),
  KEY ix_att_person (tenant_id, person_type, person_id, event_date),
  CONSTRAINT fk_att_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Prevents the cron job from emailing the same report twice.
CREATE TABLE IF NOT EXISTS report_log (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tenant_id    INT UNSIGNED NOT NULL,
  period       ENUM('daily','weekly','monthly') NOT NULL,
  period_start DATE NOT NULL,
  sent_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_report (tenant_id, period, period_start),
  CONSTRAINT fk_report_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
