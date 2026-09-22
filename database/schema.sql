-- schema do Lida Facil no dialeto sqlite, usado pelos testes em memoria; o app roda no schema.mysql.sql

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    -- perfil de acesso do rbac; o check barra qualquer valor fora dos três papéis previstos
    role TEXT NOT NULL DEFAULT 'client' CHECK (role IN ('admin', 'operator', 'client')),
    created_at TEXT NOT NULL
);

-- refresh tokens guardados só como hash sha256; o token cru nunca fica no banco
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    expires_at TEXT NOT NULL,
    revoked_at TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_refresh_tokens_user ON refresh_tokens (user_id);

CREATE TABLE IF NOT EXISTS animals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    tag TEXT NOT NULL UNIQUE,
    species TEXT NOT NULL,
    sex TEXT NOT NULL,
    birth_date TEXT NULL,
    weight_kg REAL NOT NULL,
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS vaccinations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    animal_id INTEGER NOT NULL,
    vaccine_name TEXT NOT NULL,
    dose TEXT NULL,
    applied_at TEXT NOT NULL,
    notes TEXT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_vaccinations_animal ON vaccinations (animal_id);
