-- schema do Lida Facil no dialeto mysql 8, usado quando DB_DRIVER=mysql
-- os testes no schema.sql que é sqlite em memoria; o app roda via docker compose

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    -- utf8mb4_bin deixa o email sensivel a caixa, igual ao sqlite dos testes, para a unicidade não divergir
    email VARCHAR(255) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    -- perfil de acesso do rbac; o check barra qualquer valor fora dos três papéis previstos
    role VARCHAR(20) NOT NULL DEFAULT 'client' CHECK (role IN ('admin', 'operator', 'client')),
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- refresh tokens guardados só como hash sha256; o token cru nunca fica no banco
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_refresh_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS animals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    -- mesmo motivo do email: brinco sensível a caixa para casar com a unicidade do sqlite
    tag VARCHAR(255) COLLATE utf8mb4_bin NOT NULL UNIQUE,
    species VARCHAR(20) NOT NULL,
    sex VARCHAR(20) NOT NULL,
    birth_date DATE NULL,
    weight_kg DOUBLE NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- o innodb ja cria indice automatico para a coluna de chave estrangeira, entao nao declarado um a parte
CREATE TABLE IF NOT EXISTS vaccinations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    animal_id INT NOT NULL,
    vaccine_name VARCHAR(255) NOT NULL,
    dose VARCHAR(255) NULL,
    applied_at DATE NOT NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_vaccinations_animal FOREIGN KEY (animal_id) REFERENCES animals (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
