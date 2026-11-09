CREATE TABLE communities (
 id CHAR(32) PRIMARY KEY,
 name VARCHAR(200) NOT NULL,
 created_at DATETIME(6) NOT NULL
) ENGINE=InnoDB;
INSERT INTO communities VALUES ('11111111111111111111111111111111','Existing story collection',UTC_TIMESTAMP(6));

CREATE TABLE accounts (
 id CHAR(32) PRIMARY KEY,
 username VARCHAR(100) NOT NULL UNIQUE,
 display_name VARCHAR(200) NOT NULL,
 email VARCHAR(254) NOT NULL,
 password_hash VARCHAR(255) NOT NULL,
 active TINYINT(1) NOT NULL DEFAULT 1,
 is_owner TINYINT(1) NOT NULL DEFAULT 0,
 auth_version INT NOT NULL DEFAULT 1,
 created_at DATETIME(6) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE memberships (
 account_id CHAR(32) NOT NULL,
 community_id CHAR(32) NOT NULL,
 role ENUM('ADMIN','STEWARD','REVIEWER') NOT NULL,
 PRIMARY KEY(account_id,community_id),
 FOREIGN KEY(account_id) REFERENCES accounts(id),
 FOREIGN KEY(community_id) REFERENCES communities(id)
) ENGINE=InnoDB;
CREATE TABLE site_state (
 id INT PRIMARY KEY,
 bootstrap_account_id CHAR(32) NULL,
 FOREIGN KEY(bootstrap_account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;
INSERT INTO site_state VALUES(1,NULL);
CREATE TABLE account_login_guard (
 identity_hash CHAR(64) PRIMARY KEY,
 failures INT NOT NULL DEFAULT 0,
 blocked_until DATETIME NULL
) ENGINE=InnoDB;

ALTER TABLE stories ADD community_id CHAR(32) NOT NULL DEFAULT '11111111111111111111111111111111',
 ADD sharing_state ENUM('ACTIVE','PAUSED','WITHDRAWN') NOT NULL DEFAULT 'ACTIVE',
 ADD revision INT NOT NULL DEFAULT 1,
 ADD preferred_credit TEXT NULL,
 ADD updated_at DATETIME(6) NULL,
 ADD CONSTRAINT stories_community_fk FOREIGN KEY(community_id) REFERENCES communities(id);
CREATE TABLE story_revisions (
 story_id CHAR(9) NOT NULL,
 revision INT NOT NULL,
 snapshot MEDIUMTEXT NOT NULL,
 actor_id CHAR(32) NULL,
 reason VARCHAR(2000) NOT NULL,
 created_at DATETIME(6) NOT NULL,
 PRIMARY KEY(story_id,revision),
 FOREIGN KEY(story_id) REFERENCES stories(id),
 FOREIGN KEY(actor_id) REFERENCES accounts(id)
) ENGINE=InnoDB;
ALTER TABLE permission_requests ADD email_updates TINYINT(1) NOT NULL DEFAULT 0,
 ADD counter_response ENUM('ACCEPTED','DECLINED') NULL,
 ADD counter_reply TEXT NULL,
 ADD counter_version INT NOT NULL DEFAULT 0;
CREATE TABLE request_tokens (
 token_hash CHAR(64) PRIMARY KEY,
 request_id CHAR(32) NOT NULL,
 expires_at DATETIME NOT NULL,
 FOREIGN KEY(request_id) REFERENCES permission_requests(id),
 INDEX(request_id)
) ENGINE=InnoDB;
CREATE TABLE request_events (
 id BIGINT AUTO_INCREMENT PRIMARY KEY,
 request_id CHAR(32) NOT NULL,
 event VARCHAR(40) NOT NULL,
 note TEXT NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(request_id) REFERENCES permission_requests(id)
) ENGINE=InnoDB;
CREATE TABLE mail_outbox (
 id CHAR(32) PRIMARY KEY,
 request_id CHAR(32) NOT NULL,
 community_id CHAR(32) NOT NULL,
 recipient VARCHAR(254) NOT NULL,
 subject VARCHAR(200) NOT NULL,
 body TEXT NOT NULL,
 state ENUM('PENDING','SENDING','SENT','FAILED') NOT NULL DEFAULT 'PENDING',
 attempts INT NOT NULL DEFAULT 0,
 lease_until DATETIME NULL,
 created_at DATETIME(6) NOT NULL,
 sent_at DATETIME(6) NULL,
 FOREIGN KEY(community_id) REFERENCES communities(id),
 FOREIGN KEY(request_id) REFERENCES permission_requests(id),
 INDEX(state,created_at)
) ENGINE=InnoDB;
