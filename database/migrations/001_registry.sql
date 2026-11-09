CREATE TABLE IF NOT EXISTS creators (
 id CHAR(32) PRIMARY KEY, type ENUM('PERSON','COMMUNITY') NOT NULL,
 name VARCHAR(200) NOT NULL, representative_name VARCHAR(200) NOT NULL,
 language VARCHAR(100) NOT NULL, created_at DATETIME(6) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS stories (
 id CHAR(9) PRIMARY KEY, creator_id CHAR(32) NOT NULL,
 title VARCHAR(200) NOT NULL, media_type ENUM('text','image','audio','video') NOT NULL,
 text_body MEDIUMTEXT NULL, file_path VARCHAR(100) NULL, mime_type VARCHAR(100) NULL,
 language VARCHAR(100) NOT NULL, place_coarse VARCHAR(100) NOT NULL,
 context_note TEXT NOT NULL, sensitivity ENUM('OPEN','RESTRICTED') NOT NULL,
 status ENUM('UNVERIFIED','AUTHOR_REVIEWED','COMMUNITY_ENDORSED') NOT NULL DEFAULT 'UNVERIFIED',
 content_hash CHAR(64) NOT NULL, created_at DATETIME(6) NOT NULL,
 FOREIGN KEY (creator_id) REFERENCES creators(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS contributors (
 id CHAR(32) PRIMARY KEY, story_id CHAR(9) NOT NULL, name VARCHAR(200) NOT NULL,
 role VARCHAR(100) NOT NULL, is_community_fund BOOLEAN NOT NULL,
 FOREIGN KEY (story_id) REFERENCES stories(id), INDEX(story_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS consents (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, contributor_id CHAR(32) NOT NULL,
 purpose ENUM('RECORD','PUBLISH','TRANSLATE','COMMERCIAL','AI_TRAINING') NOT NULL,
 granted BOOLEAN NOT NULL, recorded_at DATETIME(6) NOT NULL,
 FOREIGN KEY (contributor_id) REFERENCES contributors(id), INDEX(contributor_id,purpose,id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS licenses (
 story_id CHAR(9) PRIMARY KEY, preset ENUM('FREE_WITH_CREDIT','EDUCATION_FREE_COMMERCIAL_BY_PERMISSION','ALL_BY_PERMISSION') NOT NULL,
 ai_training_allowed BOOLEAN NOT NULL, contact_email VARCHAR(254) NOT NULL, notes TEXT NOT NULL,
 FOREIGN KEY (story_id) REFERENCES stories(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS split_shares (
 story_id CHAR(9) NOT NULL, contributor_id CHAR(32) NOT NULL, bps INT NOT NULL CHECK(bps BETWEEN 0 AND 10000),
 PRIMARY KEY(story_id,contributor_id), FOREIGN KEY(story_id) REFERENCES stories(id), FOREIGN KEY(contributor_id) REFERENCES contributors(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS permission_requests (
 id CHAR(32) PRIMARY KEY, story_id CHAR(9) NOT NULL, requester_name VARCHAR(200) NOT NULL,
 requester_contact VARCHAR(254) NOT NULL, intended_use TEXT NOT NULL, is_commercial BOOLEAN NOT NULL,
 is_ai_training BOOLEAN NOT NULL, proposed_fee_minor BIGINT NULL CHECK(proposed_fee_minor >= 0),
 currency CHAR(3) NOT NULL, status ENUM('PENDING','APPROVED','DECLINED','COUNTERED') NOT NULL DEFAULT 'PENDING',
 counter_fee_minor BIGINT NULL CHECK(counter_fee_minor >= 0), decision_note TEXT NULL,
 final_fee_minor BIGINT NULL CHECK(final_fee_minor >= 0), approval_snapshot MEDIUMTEXT NULL,
 created_at DATETIME(6) NOT NULL, decided_at DATETIME(6) NULL,
 FOREIGN KEY(story_id) REFERENCES stories(id), INDEX(story_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS ledger_entries (
 id CHAR(32) PRIMARY KEY, story_id CHAR(9) NOT NULL, permission_req_id CHAR(32) NOT NULL,
 contributor_id CHAR(32) NOT NULL, amount_minor BIGINT NOT NULL, currency CHAR(3) NOT NULL,
 kind ENUM('MOCK_PAYMENT','MOCK_ADJUSTMENT') NOT NULL, original_entry_id CHAR(32) NULL,
 payment_key VARCHAR(100) NULL UNIQUE, note TEXT NOT NULL, created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(story_id) REFERENCES stories(id), FOREIGN KEY(permission_req_id) REFERENCES permission_requests(id),
 FOREIGN KEY(contributor_id) REFERENCES contributors(id), FOREIGN KEY(original_entry_id) REFERENCES ledger_entries(id), INDEX(story_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS claims (
 id CHAR(32) PRIMARY KEY, story_id CHAR(9) NOT NULL, reporter_name VARCHAR(200) NOT NULL,
 reporter_contact VARCHAR(254) NOT NULL, reason TEXT NOT NULL,
 status ENUM('OPEN','RESOLVED') NOT NULL DEFAULT 'OPEN', created_at DATETIME(6) NOT NULL,
 FOREIGN KEY(story_id) REFERENCES stories(id), INDEX(story_id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS login_guard (
 id TINYINT PRIMARY KEY, failures INT NOT NULL DEFAULT 0, blocked_until DATETIME NULL
) ENGINE=InnoDB;
INSERT IGNORE INTO login_guard(id) VALUES(1);
