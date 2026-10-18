-- Apply once to existing installations before deploying password/Google sign-in.
ALTER TABLE users ADD COLUMN password_hash varchar(255) DEFAULT NULL,
  ADD COLUMN google_sub varchar(255) DEFAULT NULL,
  ADD UNIQUE KEY idx_users_google_sub (google_sub);
