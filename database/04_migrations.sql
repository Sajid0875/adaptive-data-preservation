-- 04_migrations.sql
-- Run this to update an existing database

BEGIN;

ALTER TABLE preservation_decisions ADD COLUMN IF NOT EXISTS is_manual BOOLEAN DEFAULT FALSE;
ALTER TABLE preservation_decisions ADD COLUMN IF NOT EXISTS manual_reason TEXT;
ALTER TABLE preservation_decisions ADD COLUMN IF NOT EXISTS overridden_at TIMESTAMPTZ NULL;
ALTER TABLE preservation_decisions ADD COLUMN IF NOT EXISTS reason TEXT;

COMMIT;
