<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private PDO $pdo;

    public function __construct(string $path)
    {
        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $this->pdo = new PDO('sqlite:' . $path, options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
        $this->pdo->exec('PRAGMA journal_mode = WAL');
        $this->migrate();
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                password_hash TEXT NOT NULL,
                name TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS parks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                slug TEXT NOT NULL UNIQUE,
                location TEXT NOT NULL DEFAULT '',
                attention_points TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS people (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL CHECK (type IN ('staff','guest')),
                name TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT '',
                email TEXT NOT NULL DEFAULT '',
                phone TEXT NOT NULL DEFAULT '',
                is_active INTEGER NOT NULL DEFAULT 1,
                notes TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
            CREATE INDEX IF NOT EXISTS idx_people_type ON people(type);

            CREATE TABLE IF NOT EXISTS person_parks (
                person_id INTEGER NOT NULL,
                park_id INTEGER NOT NULL,
                PRIMARY KEY (person_id, park_id),
                FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
                FOREIGN KEY (park_id) REFERENCES parks(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_person_parks_park ON person_parks(park_id);

            CREATE TABLE IF NOT EXISTS items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                park_id INTEGER NOT NULL,
                category TEXT NOT NULL CHECK (category IN ('personeel','park','gasten','taken')),
                type TEXT NOT NULL CHECK (type IN ('notitie','afspraak','taak')),
                person_id INTEGER,
                title TEXT NOT NULL,
                body TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','in_uitvoering','afgerond','gearchiveerd')),
                due_date TEXT,
                completed_at TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (park_id) REFERENCES parks(id) ON DELETE CASCADE,
                FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_items_park ON items(park_id);
            CREATE INDEX IF NOT EXISTS idx_items_person ON items(person_id);
            CREATE INDEX IF NOT EXISTS idx_items_category_status ON items(category, status);
            CREATE INDEX IF NOT EXISTS idx_items_due_date ON items(due_date);

            CREATE TABLE IF NOT EXISTS absences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                person_id INTEGER NOT NULL,
                start_date TEXT NOT NULL,
                end_date TEXT,
                reason TEXT NOT NULL DEFAULT '',
                status TEXT NOT NULL DEFAULT 'lopend' CHECK (status IN ('lopend','hersteld','langdurig')),
                notes TEXT NOT NULL DEFAULT '',
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_absences_person ON absences(person_id);

            CREATE TABLE IF NOT EXISTS performance_reviews (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                person_id INTEGER NOT NULL,
                review_date TEXT NOT NULL,
                type TEXT NOT NULL DEFAULT 'functioneringsgesprek'
                    CHECK (type IN ('functioneringsgesprek','beoordelingsgesprek','proefperiode','overig')),
                summary TEXT NOT NULL DEFAULT '',
                follow_up_date TEXT,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_reviews_person ON performance_reviews(person_id);

            CREATE TABLE IF NOT EXISTS departments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE COLLATE NOCASE,
                description TEXT NOT NULL DEFAULT '',
                sort_order INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS playbooks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                department_id INTEGER NOT NULL,
                park_id INTEGER,
                leader_person_id INTEGER,
                leader_name TEXT NOT NULL DEFAULT '',
                description TEXT NOT NULL DEFAULT '',
                share_token TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                FOREIGN KEY (park_id) REFERENCES parks(id) ON DELETE SET NULL,
                FOREIGN KEY (leader_person_id) REFERENCES people(id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_playbooks_department ON playbooks(department_id);
            CREATE INDEX IF NOT EXISTS idx_playbooks_park ON playbooks(park_id);

            CREATE TABLE IF NOT EXISTS playbook_steps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                playbook_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                body TEXT NOT NULL DEFAULT '',
                schedule_type TEXT NOT NULL CHECK (schedule_type IN ('op_datum','vanaf_datum')),
                date TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','afgerond')),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (playbook_id) REFERENCES playbooks(id) ON DELETE CASCADE
            );
            CREATE INDEX IF NOT EXISTS idx_playbook_steps_playbook ON playbook_steps(playbook_id);
            CREATE INDEX IF NOT EXISTS idx_playbook_steps_date ON playbook_steps(date);
        SQL);

        $hasDepartments = (int) $this->pdo->query('SELECT COUNT(*) FROM departments')->fetchColumn() > 0;
        if (!$hasDepartments) {
            $stmt = $this->pdo->prepare('INSERT INTO departments (name, sort_order) VALUES (?, ?)');
            foreach (['Receptie', 'Housekeeping', 'Technische dienst'] as $index => $name) {
                $stmt->execute([$name, $index]);
            }
        }

        $peopleColumns = $this->pdo->query('PRAGMA table_info(people)')->fetchAll();
        $peopleColumnNames = array_column($peopleColumns, 'name');
        if (in_array('park_id', $peopleColumnNames, true)) {
            $this->pdo->exec(<<<'SQL'
                INSERT OR IGNORE INTO person_parks (person_id, park_id)
                SELECT id, park_id FROM people WHERE park_id IS NOT NULL
            SQL);
            $this->pdo->exec('ALTER TABLE people DROP COLUMN park_id');
        }
    }
}
