-- 1. Create rate_limits table
CREATE TABLE IF NOT EXISTS rate_limits (
    bucket VARCHAR(64) NOT NULL PRIMARY KEY,
    attempts INT NOT NULL DEFAULT 0,
    expires_at INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Add missing indexes
ALTER TABLE aliases
    ADD INDEX idx_aliases_tenant_id (tenant_id),
    ADD INDEX idx_aliases_domain_id (domain_id),
    ADD INDEX idx_aliases_destination_mailbox_id (destination_mailbox_id);

ALTER TABLE forwards
    ADD INDEX idx_forwards_tenant_id (tenant_id),
    ADD INDEX idx_forwards_domain_id (domain_id);

ALTER TABLE mailbox_password_history
    ADD INDEX idx_mph_mailbox_id (mailbox_id);

ALTER TABLE dkim_keys
    ADD INDEX idx_dkim_keys_domain_id (domain_id);

ALTER TABLE spam_policies
    ADD INDEX idx_spam_policies_tenant_id (tenant_id),
    ADD INDEX idx_spam_policies_domain_id (domain_id),
    ADD INDEX idx_spam_policies_mailbox_id (mailbox_id);

ALTER TABLE users
    ADD INDEX idx_users_role_email (role, email),
    ADD INDEX idx_users_role_deleted_at (role, deleted_at);

