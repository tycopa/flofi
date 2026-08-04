-- FloFi personal budget tracker schema (PostgreSQL)
-- Run once against your database to create all tables.

CREATE TABLE IF NOT EXISTS accounts (
    id                  SERIAL PRIMARY KEY,
    username            VARCHAR(60)  NOT NULL UNIQUE,
    full_name           VARCHAR(120) NOT NULL,
    email               VARCHAR(200) NOT NULL UNIQUE,
    phone               VARCHAR(30),
    country             CHAR(2)      NOT NULL DEFAULT 'US',
    password_hash       TEXT         NOT NULL,
    is_admin            BOOLEAN      NOT NULL DEFAULT false,
    is_disabled         BOOLEAN      NOT NULL DEFAULT false,
    theme               VARCHAR(20)  NOT NULL DEFAULT 'orange',
    must_reset_password BOOLEAN      NOT NULL DEFAULT false,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id          SERIAL PRIMARY KEY,
    account_id  INT         NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    token       TEXT        NOT NULL UNIQUE,
    expires_at  TIMESTAMPTZ NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS password_resets (
    id          SERIAL PRIMARY KEY,
    account_id  INT         NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    token       TEXT        NOT NULL UNIQUE,
    expires_at  TIMESTAMPTZ NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS categories (
    id          SERIAL PRIMARY KEY,
    account_id  INT         NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    type        VARCHAR(10) NOT NULL CHECK (type IN ('revenue','expense')),
    name        VARCHAR(100) NOT NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (account_id, type, name)
);

CREATE TABLE IF NOT EXISTS transactions (
    id           SERIAL PRIMARY KEY,
    account_id   INT         NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    category_id  INT         NOT NULL REFERENCES categories(id) ON DELETE RESTRICT,
    type         VARCHAR(10) NOT NULL CHECK (type IN ('revenue','expense')),
    amount_cents INT         NOT NULL CHECK (amount_cents > 0),
    note         VARCHAR(255),
    tx_date      DATE        NOT NULL DEFAULT CURRENT_DATE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_transactions_account_date ON transactions (account_id, tx_date DESC);
CREATE INDEX IF NOT EXISTS idx_transactions_category     ON transactions (category_id);

CREATE TABLE IF NOT EXISTS admin_log (
    id          SERIAL PRIMARY KEY,
    admin_id    INT,
    admin_name  VARCHAR(120) NOT NULL,
    action      TEXT         NOT NULL,
    target_user INT,
    details     TEXT,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT NOW()
);
