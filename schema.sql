-- FloFi personal budget tracker schema (SQL Server)
-- Run once: sqlcmd -S localhost -d flofi -i schema.sql

CREATE TABLE accounts (
    id                  INT IDENTITY(1,1) PRIMARY KEY,
    username            NVARCHAR(60)  NOT NULL UNIQUE,
    full_name           NVARCHAR(120) NOT NULL,
    email               NVARCHAR(200) NOT NULL UNIQUE,
    phone               NVARCHAR(30)  NULL,
    country             NCHAR(2)      NOT NULL DEFAULT 'US',
    password_hash       NVARCHAR(MAX) NOT NULL,
    is_admin            BIT           NOT NULL DEFAULT 0,
    is_disabled         BIT           NOT NULL DEFAULT 0,
    theme               NVARCHAR(20)  NOT NULL DEFAULT 'orange',
    must_reset_password BIT           NOT NULL DEFAULT 0,
    created_at          DATETIMEOFFSET NOT NULL DEFAULT GETUTCDATE()
);

CREATE TABLE remember_tokens (
    id          INT IDENTITY(1,1) PRIMARY KEY,
    account_id  INT           NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    token       NVARCHAR(MAX) NOT NULL UNIQUE,
    expires_at  DATETIMEOFFSET NOT NULL,
    created_at  DATETIMEOFFSET NOT NULL DEFAULT GETUTCDATE()
);

CREATE TABLE password_resets (
    id          INT IDENTITY(1,1) PRIMARY KEY,
    account_id  INT           NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    token       NVARCHAR(MAX) NOT NULL UNIQUE,
    expires_at  DATETIMEOFFSET NOT NULL,
    created_at  DATETIMEOFFSET NOT NULL DEFAULT GETUTCDATE()
);

CREATE TABLE categories (
    id          INT IDENTITY(1,1) PRIMARY KEY,
    account_id  INT           NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
    type        NVARCHAR(10)  NOT NULL CHECK (type IN ('revenue','expense')),
    name        NVARCHAR(100) NOT NULL,
    created_at  DATETIMEOFFSET NOT NULL DEFAULT GETUTCDATE(),
    CONSTRAINT uq_categories UNIQUE (account_id, type, name)
);

CREATE TABLE transactions (
    id           INT IDENTITY(1,1) PRIMARY KEY,
    account_id   INT           NOT NULL REFERENCES accounts(id) ON DELETE NO ACTION,
    category_id  INT           NOT NULL REFERENCES categories(id) ON DELETE NO ACTION,
    type         NVARCHAR(10)  NOT NULL CHECK (type IN ('revenue','expense')),
    amount_cents INT           NOT NULL CHECK (amount_cents > 0),
    note         NVARCHAR(255) NULL,
    tx_date      DATE          NOT NULL DEFAULT CAST(GETUTCDATE() AS DATE),
    created_at   DATETIMEOFFSET NOT NULL DEFAULT GETUTCDATE()
);

CREATE INDEX idx_transactions_account_date ON transactions (account_id, tx_date DESC);
CREATE INDEX idx_transactions_category     ON transactions (category_id);

CREATE TABLE admin_log (
    id          INT IDENTITY(1,1) PRIMARY KEY,
    admin_id    INT           NULL,
    admin_name  NVARCHAR(120) NOT NULL,
    action      NVARCHAR(MAX) NOT NULL,
    target_user INT           NULL,
    details     NVARCHAR(MAX) NULL,
    created_at  DATETIMEOFFSET NOT NULL DEFAULT GETUTCDATE()
);
