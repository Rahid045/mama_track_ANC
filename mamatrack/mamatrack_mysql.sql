-- In InfinityFree phpMyAdmin, select the database you created before importing this file.

-- Drop tables only if you want a full reset
-- DROP TABLE IF EXISTS audit_log;
-- DROP TABLE IF EXISTS notifications;
-- DROP TABLE IF EXISTS visits;
-- DROP TABLE IF EXISTS appointments;
-- DROP TABLE IF EXISTS patients;
-- DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30) DEFAULT NULL,
  role ENUM('admin', 'doctor', 'nurse') NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  must_change TINYINT(1) NOT NULL DEFAULT 0,
  failed_logins INT NOT NULL DEFAULT 0,
  locked_until DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE patients (
  id INT AUTO_INCREMENT PRIMARY KEY,
  mrn VARCHAR(50) UNIQUE DEFAULT NULL,
  name VARCHAR(150) NOT NULL,
  dob DATE NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  village VARCHAR(150) DEFAULT NULL,
  district VARCHAR(150) DEFAULT NULL,
  next_of_kin VARCHAR(150) DEFAULT NULL,
  nok_phone VARCHAR(30) DEFAULT NULL,
  lmp DATE NOT NULL,
  edd DATE DEFAULT NULL,
  gravida INT NOT NULL DEFAULT 1,
  parity INT NOT NULL DEFAULT 0,
  hiv_status VARCHAR(20) NOT NULL DEFAULT 'unknown',
  history TEXT DEFAULT NULL,
  doctor_id INT DEFAULT NULL,
  risk_level VARCHAR(20) NOT NULL DEFAULT 'unassessed',
  risk_score INT NOT NULL DEFAULT 0,
  created_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_patients_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_patients_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE appointments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  doctor_id INT DEFAULT NULL,
  appt_date DATE NOT NULL,
  appt_time TIME DEFAULT NULL,
  reason TEXT DEFAULT NULL,
  status ENUM('scheduled', 'attended', 'missed', 'rescheduled', 'cancelled') NOT NULL DEFAULT 'scheduled',
  token VARCHAR(255) DEFAULT NULL UNIQUE,
  rescheduled_from INT DEFAULT NULL,
  missed_at DATETIME DEFAULT NULL,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_appointments_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_appointments_doctor FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_appointments_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE visits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  patient_id INT NOT NULL,
  appointment_id INT DEFAULT NULL,
  visit_date DATE NOT NULL,
  gest_weeks INT DEFAULT NULL,
  systolic INT DEFAULT NULL,
  diastolic INT DEFAULT NULL,
  heart_rate INT DEFAULT NULL,
  weight DECIMAL(6,2) DEFAULT NULL,
  temperature DECIMAL(4,2) DEFAULT NULL,
  fundal_height DECIMAL(5,2) DEFAULT NULL,
  fetal_hr INT DEFAULT NULL,
  hemoglobin DECIMAL(5,2) DEFAULT NULL,
  urine_protein VARCHAR(50) DEFAULT NULL,
  notes TEXT DEFAULT NULL,
  recorded_by INT DEFAULT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_visits_patient FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
  CONSTRAINT fk_visits_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL,
  CONSTRAINT fk_visits_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT DEFAULT NULL,
  recipient VARCHAR(150) DEFAULT NULL,
  channel VARCHAR(50) DEFAULT NULL,
  to_addr VARCHAR(255) DEFAULT NULL,
  message TEXT DEFAULT NULL,
  status VARCHAR(50) DEFAULT NULL,
  response TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_notifications_appointment FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  user_name VARCHAR(150) DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  detail TEXT DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX ix_visits_patient ON visits(patient_id, visit_date);
CREATE INDEX ix_appt_status ON appointments(status, appt_date);
CREATE INDEX ix_patients_doctor ON patients(doctor_id);

-- Default admin account for first login
-- Login: admin@mamatrack.local
-- Password: Admin@123
INSERT INTO users (name, email, phone, role, password_hash, active, must_change, failed_logins, created_at)
VALUES (
  'System Administrator',
  'admin@mamatrack.local',
  NULL,
  'admin',
  '$2b$10$MDxivFamM90.zo8BFVKVPeC7H2bKAlVk8WoLjt7yM0hgduOfvvjzW',
  1,
  1,
  0,
  NOW()
);

-- Optional demo users if you want a quick test environment
-- INSERT INTO users (name, email, phone, role, password_hash, active, must_change, failed_logins, created_at)
-- VALUES ('Dr. Sarah Nakato', 'sarah@mamatrack.local', '+256700000001', 'doctor', '$2y$10$...', 1, 0, 0, NOW());
-- INSERT INTO users (name, email, phone, role, password_hash, active, must_change, failed_logins, created_at)
-- VALUES ('Dr. Peter Okello', 'peter@mamatrack.local', '+256700000002', 'doctor', '$2y$10$...', 1, 0, 0, NOW());
-- INSERT INTO users (name, email, phone, role, password_hash, active, must_change, failed_logins, created_at)
-- VALUES ('Grace Namatovu', 'grace@mamatrack.local', '+256700000003', 'nurse', '$2y$10$...', 1, 0, 0, NOW());
