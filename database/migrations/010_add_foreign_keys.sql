-- 1. Clean up orphaned records
DELETE FROM tenants WHERE package_id NOT IN (SELECT id FROM packages);
DELETE FROM domains WHERE tenant_id NOT IN (SELECT id FROM tenants);
DELETE FROM users WHERE tenant_id NOT IN (SELECT id FROM tenants);
DELETE FROM mailboxes WHERE tenant_id NOT IN (SELECT id FROM tenants) OR domain_id NOT IN (SELECT id FROM domains);
DELETE FROM aliases WHERE tenant_id NOT IN (SELECT id FROM tenants) OR domain_id NOT IN (SELECT id FROM domains);
DELETE FROM forwards WHERE tenant_id NOT IN (SELECT id FROM tenants) OR domain_id NOT IN (SELECT id FROM domains);
DELETE FROM mail_groups WHERE tenant_id NOT IN (SELECT id FROM tenants) OR domain_id NOT IN (SELECT id FROM domains);
DELETE FROM quota_usage WHERE mailbox_id NOT IN (SELECT id FROM mailboxes);
DELETE FROM mailbox_password_history WHERE mailbox_id NOT IN (SELECT id FROM mailboxes);
DELETE FROM user_password_history WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM api_tokens WHERE user_id NOT IN (SELECT id FROM users);
DELETE FROM tenant_subscriptions WHERE tenant_id NOT IN (SELECT id FROM tenants);
DELETE FROM tenant_lifecycle_events WHERE tenant_id NOT IN (SELECT id FROM tenants);
DELETE FROM spam_policies WHERE tenant_id IS NOT NULL AND tenant_id NOT IN (SELECT id FROM tenants);
DELETE FROM spam_policies WHERE domain_id IS NOT NULL AND domain_id NOT IN (SELECT id FROM domains);
DELETE FROM dns_checks WHERE domain_id NOT IN (SELECT id FROM domains);
DELETE FROM dkim_keys WHERE domain_id NOT IN (SELECT id FROM domains);

-- 2. Add Foreign Keys
ALTER TABLE tenants
    ADD CONSTRAINT fk_tenants_package_id FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE RESTRICT;

ALTER TABLE domains
    ADD CONSTRAINT fk_domains_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

ALTER TABLE users
    ADD CONSTRAINT fk_users_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

ALTER TABLE mailboxes
    ADD CONSTRAINT fk_mailboxes_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_mailboxes_domain_id FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE;

ALTER TABLE aliases
    ADD CONSTRAINT fk_aliases_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_aliases_domain_id FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE;

ALTER TABLE forwards
    ADD CONSTRAINT fk_forwards_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_forwards_domain_id FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE;

ALTER TABLE mail_groups
    ADD CONSTRAINT fk_mail_groups_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_mail_groups_domain_id FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE;

ALTER TABLE quota_usage
    ADD CONSTRAINT fk_quota_usage_mailbox_id FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE;

ALTER TABLE mailbox_password_history
    ADD CONSTRAINT fk_mailbox_password_history_mailbox_id FOREIGN KEY (mailbox_id) REFERENCES mailboxes(id) ON DELETE CASCADE;

ALTER TABLE user_password_history
    ADD CONSTRAINT fk_user_password_history_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE api_tokens
    ADD CONSTRAINT fk_api_tokens_user_id FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;

ALTER TABLE tenant_subscriptions
    ADD CONSTRAINT fk_tenant_subscriptions_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

ALTER TABLE tenant_lifecycle_events
    ADD CONSTRAINT fk_tenant_lifecycle_events_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE;

ALTER TABLE spam_policies
    ADD CONSTRAINT fk_spam_policies_tenant_id FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    ADD CONSTRAINT fk_spam_policies_domain_id FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE;

ALTER TABLE dns_checks
    ADD CONSTRAINT fk_dns_checks_domain_id FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE;

ALTER TABLE dkim_keys
    ADD CONSTRAINT fk_dkim_keys_domain_id FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE;

