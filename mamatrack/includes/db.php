<?php
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $database = cfg('database', []);
    if (($database['driver'] ?? '') === 'mysql') {
        foreach (['host', 'name', 'user', 'password'] as $key) {
            if (($database[$key] ?? '') === '') throw new RuntimeException("MySQL setting '$key' is missing in config.local.php.");
        }
        $pdo = new PDO('mysql:host=' . $database['host'] . ';port=' . ($database['port'] ?? 3306)
            . ';dbname=' . $database['name'] . ';charset=utf8mb4', $database['user'], $database['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
    } else {
        $path = cfg('db_path');
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA journal_mode=WAL;');
    }
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    // InfinityFree MySQL tables are imported once through phpMyAdmin using mamatrack_mysql.sql.
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') return;
    $id = 'INTEGER PRIMARY KEY';
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS users(
      id $id, name TEXT NOT NULL, email TEXT UNIQUE NOT NULL, phone TEXT,
      role TEXT NOT NULL CHECK(role IN('admin','doctor','nurse')),
      password_hash TEXT NOT NULL, active INTEGER DEFAULT 1, must_change INTEGER DEFAULT 0,
      failed_logins INTEGER DEFAULT 0, locked_until TEXT, created_at TEXT);
    CREATE TABLE IF NOT EXISTS patients(
      id $id, mrn TEXT UNIQUE, name TEXT NOT NULL, dob TEXT NOT NULL,
      phone TEXT, email TEXT, village TEXT, district TEXT, next_of_kin TEXT, nok_phone TEXT,
      lmp TEXT NOT NULL, edd TEXT, gravida INTEGER DEFAULT 1, parity INTEGER DEFAULT 0,
      hiv_status TEXT DEFAULT 'unknown', history TEXT,
      doctor_id INTEGER REFERENCES users(id),
      risk_level TEXT DEFAULT 'unassessed', risk_score INTEGER DEFAULT 0,
      created_by INTEGER, created_at TEXT);
    CREATE TABLE IF NOT EXISTS appointments(
      id $id, patient_id INTEGER NOT NULL REFERENCES patients(id),
      doctor_id INTEGER, appt_date TEXT NOT NULL, appt_time TEXT, reason TEXT,
      status TEXT DEFAULT 'scheduled' CHECK(status IN('scheduled','attended','missed','rescheduled','cancelled')),
      token TEXT UNIQUE, rescheduled_from INTEGER, missed_at TEXT, reminder_sent INTEGER DEFAULT 0,
      created_by INTEGER, created_at TEXT);
    CREATE TABLE IF NOT EXISTS visits(
      id $id, patient_id INTEGER NOT NULL REFERENCES patients(id),
      appointment_id INTEGER, visit_date TEXT NOT NULL, gest_weeks INTEGER,
      systolic INTEGER, diastolic INTEGER, heart_rate INTEGER, weight REAL, temperature REAL,
      fundal_height REAL, fetal_hr INTEGER, hemoglobin REAL, urine_protein TEXT, notes TEXT,
      recorded_by INTEGER, created_at TEXT);
    CREATE TABLE IF NOT EXISTS notifications(
      id $id, appointment_id INTEGER, recipient TEXT, channel TEXT,
      to_addr TEXT, message TEXT, status TEXT, response TEXT, created_at TEXT);
    CREATE TABLE IF NOT EXISTS audit_log(
      id $id, user_id INTEGER, user_name TEXT, action TEXT, detail TEXT, ip TEXT, created_at TEXT);
    CREATE TABLE IF NOT EXISTS sessions(id TEXT PRIMARY KEY, data TEXT NOT NULL, updated_at TEXT NOT NULL);
    CREATE INDEX IF NOT EXISTS ix_visits_patient ON visits(patient_id, visit_date);
    CREATE INDEX IF NOT EXISTS ix_appt_status ON appointments(status, appt_date);
    CREATE INDEX IF NOT EXISTS ix_patients_doctor ON patients(doctor_id);
    ");
    // First run: create the initial administrator (must change password at first login).
    if ((int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
        $pdo->prepare('INSERT INTO users(name,email,role,password_hash,must_change,created_at) VALUES(?,?,?,?,1,?)')
            ->execute(['System Administrator', 'admin@mamatrack.local', 'admin', password_hash('Admin@123', PASSWORD_DEFAULT), now()]);
    }
    // Block direct web access to the data folder on Apache.
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $ht = dirname(cfg('db_path')) . '/.htaccess';
        if (!file_exists($ht)) @file_put_contents($ht, "Require all denied\nDeny from all\n");
    }
}
