-- PPタスク管理 v2
-- 既存 user_tbl / remember_token_tbl を使用
-- display_name が既に存在する場合、ALTER TABLE の1行は実行しない

ALTER TABLE user_tbl ADD COLUMN display_name VARCHAR(100) NOT NULL DEFAULT '' AFTER user_password;

CREATE TABLE IF NOT EXISTS projects (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 description TEXT NOT NULL,
 owner_user_account VARCHAR(255) NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 INDEX(owner_user_account)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_members (
 project_id INT UNSIGNED NOT NULL,
 user_account VARCHAR(255) NOT NULL,
 role ENUM('owner','member') NOT NULL DEFAULT 'member',
 joined_at DATETIME NOT NULL,
 PRIMARY KEY(project_id,user_account),
 INDEX(user_account)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS project_storage (
 project_id INT UNSIGNED PRIMARY KEY,
 storage_type VARCHAR(32) NOT NULL DEFAULT 'local',
 base_path VARCHAR(1000) NOT NULL DEFAULT '',
 config_json TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tasks (
 project_id INT UNSIGNED NOT NULL,
 id VARCHAR(100) NOT NULL,
 creator_user_account VARCHAR(255) NOT NULL,
 name VARCHAR(200) NOT NULL,
 summary TEXT NOT NULL,
 status VARCHAR(30) NOT NULL DEFAULT '未着手',
 memo MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 updated_at DATETIME NOT NULL,
 PRIMARY KEY(project_id,id),
 INDEX(project_id,updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS comments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NOT NULL,
 task_id VARCHAR(100) NOT NULL,
 user_account VARCHAR(255) NOT NULL,
 display_name VARCHAR(100) NOT NULL,
 body MEDIUMTEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX(project_id,task_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attachments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NOT NULL,
 task_id VARCHAR(100) NOT NULL,
 uploaded_by VARCHAR(255) NOT NULL,
 original_name VARCHAR(255) NOT NULL,
 storage_path VARCHAR(1000) NOT NULL,
 created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 project_id INT UNSIGNED NOT NULL,
 user_account VARCHAR(255) NOT NULL,
 action VARCHAR(100) NOT NULL,
 target_type VARCHAR(50) NOT NULL DEFAULT '',
 details TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 INDEX(project_id,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
