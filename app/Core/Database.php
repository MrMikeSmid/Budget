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
                leader_person_id INTEGER,
                leader_name TEXT NOT NULL DEFAULT '',
                description TEXT NOT NULL DEFAULT '',
                share_token TEXT NOT NULL UNIQUE,
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
                FOREIGN KEY (leader_person_id) REFERENCES people(id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_playbooks_department ON playbooks(department_id);

            CREATE TABLE IF NOT EXISTS playbook_steps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                playbook_id INTEGER NOT NULL,
                park_id INTEGER,
                type TEXT NOT NULL CHECK (type IN ('eenmalig','periodiek')),
                title TEXT NOT NULL,
                body TEXT NOT NULL DEFAULT '',
                start_date TEXT NOT NULL,
                end_date TEXT NOT NULL,
                recurrence_interval TEXT CHECK (recurrence_interval IN ('dagelijks','wekelijks','maandelijks') OR recurrence_interval IS NULL),
                status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','afgerond')),
                created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (playbook_id) REFERENCES playbooks(id) ON DELETE CASCADE,
                FOREIGN KEY (park_id) REFERENCES parks(id) ON DELETE SET NULL
            );
            CREATE INDEX IF NOT EXISTS idx_playbook_steps_playbook ON playbook_steps(playbook_id);
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
            // Rebuilt via CREATE+COPY+DROP+RENAME instead of `ALTER TABLE ... DROP COLUMN`:
            // that syntax needs SQLite 3.35+ (2021), which some shared-hosting PHP builds
            // still don't bundle, and would otherwise crash every request past this point.
            $this->pdo->exec(<<<'SQL'
                INSERT OR IGNORE INTO person_parks (person_id, park_id)
                SELECT id, park_id FROM people WHERE park_id IS NOT NULL
            SQL);
            $this->pdo->exec('PRAGMA foreign_keys = OFF');
            $this->pdo->exec(<<<'SQL'
                CREATE TABLE people_new (
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
                )
            SQL);
            $this->pdo->exec(<<<'SQL'
                INSERT INTO people_new (id, type, name, role, email, phone, is_active, notes, created_at, updated_at)
                SELECT id, type, name, role, email, phone, is_active, notes, created_at, updated_at FROM people
            SQL);
            $this->pdo->exec('DROP TABLE people');
            $this->pdo->exec('ALTER TABLE people_new RENAME TO people');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_people_type ON people(type)');
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }

        $stepColumns = $this->pdo->query('PRAGMA table_info(playbook_steps)')->fetchAll();
        $stepColumnNames = array_column($stepColumns, 'name');
        if (!in_array('park_id', $stepColumnNames, true)) {
            $this->pdo->exec('ALTER TABLE playbook_steps ADD COLUMN park_id INTEGER');
        }
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_playbook_steps_park ON playbook_steps(park_id)');

        if (in_array('schedule_type', $stepColumnNames, true)) {
            // Steps used to have a single date (either a one-off "op_datum" or an
            // open-ended "vanaf_datum"); both now need a start AND end date so they can
            // be plotted on the calendar, and gain a real recurrence interval. Existing
            // rows get end_date = start_date as a safe, non-crashing default — the one
            // playbook step live in production needs a manual follow-up fix after this.
            $this->pdo->exec('PRAGMA foreign_keys = OFF');
            $this->pdo->exec(<<<'SQL'
                CREATE TABLE playbook_steps_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    playbook_id INTEGER NOT NULL,
                    park_id INTEGER,
                    type TEXT NOT NULL CHECK (type IN ('eenmalig','periodiek')),
                    title TEXT NOT NULL,
                    body TEXT NOT NULL DEFAULT '',
                    start_date TEXT NOT NULL,
                    end_date TEXT NOT NULL,
                    recurrence_interval TEXT CHECK (recurrence_interval IN ('dagelijks','wekelijks','maandelijks') OR recurrence_interval IS NULL),
                    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','afgerond')),
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            SQL);
            $this->pdo->exec(<<<'SQL'
                INSERT INTO playbook_steps_new (id, playbook_id, park_id, type, title, body, start_date, end_date, recurrence_interval, status, created_at, updated_at)
                SELECT id, playbook_id, park_id, 'eenmalig', title, body, date, date, NULL, status, created_at, updated_at
                FROM playbook_steps
            SQL);
            $this->pdo->exec('DROP TABLE playbook_steps');
            $this->pdo->exec('ALTER TABLE playbook_steps_new RENAME TO playbook_steps');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_playbook_steps_playbook ON playbook_steps(playbook_id)');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_playbook_steps_park ON playbook_steps(park_id)');
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }
        $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_playbook_steps_start_date ON playbook_steps(start_date)');

        $playbookColumns = $this->pdo->query('PRAGMA table_info(playbooks)')->fetchAll();
        $playbookColumnNames = array_column($playbookColumns, 'name');
        if (in_array('park_id', $playbookColumnNames, true)) {
            // A playbook used to belong to a single optional park; that scope now lives
            // per step instead, so push each playbook's park down onto its existing steps
            // before rebuilding the table (same DROP-COLUMN-portability reasoning as above).
            $this->pdo->exec(<<<'SQL'
                UPDATE playbook_steps
                SET park_id = (SELECT park_id FROM playbooks WHERE playbooks.id = playbook_steps.playbook_id)
                WHERE park_id IS NULL
            SQL);
            $this->pdo->exec('PRAGMA foreign_keys = OFF');
            $this->pdo->exec(<<<'SQL'
                CREATE TABLE playbooks_new (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    department_id INTEGER NOT NULL,
                    leader_person_id INTEGER,
                    leader_name TEXT NOT NULL DEFAULT '',
                    description TEXT NOT NULL DEFAULT '',
                    share_token TEXT NOT NULL UNIQUE,
                    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
                )
            SQL);
            $this->pdo->exec(<<<'SQL'
                INSERT INTO playbooks_new (id, title, department_id, leader_person_id, leader_name, description, share_token, created_at, updated_at)
                SELECT id, title, department_id, leader_person_id, leader_name, description, share_token, created_at, updated_at FROM playbooks
            SQL);
            $this->pdo->exec('DROP TABLE playbooks');
            $this->pdo->exec('ALTER TABLE playbooks_new RENAME TO playbooks');
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_playbooks_department ON playbooks(department_id)');
            $this->pdo->exec('PRAGMA foreign_keys = ON');
        }
    }
}
