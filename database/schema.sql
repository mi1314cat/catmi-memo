-- ============================================================
-- Catmi Memo 数据库初始化脚本（v2 完整版）
-- 字符集 utf8mb4：完整支持中文与 Emoji
-- 引擎 InnoDB：支持外键与级联删除
-- 执行方式：phpMyAdmin 导入，或通过 install.php 自动执行
-- 注意：与线上库一致；若线上表已存在则跳过（IF NOT EXISTS），不会破坏数据
-- ============================================================

SET NAMES utf8mb4;

-- ---------- 用户表 ----------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(32) NOT NULL COMMENT '用户名',
  password_hash VARCHAR(255) NOT NULL COMMENT 'password_hash() 生成的散列，绝不存明文',
  role VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT '角色：user 普通用户 / admin 管理员（显式指定，无自动提权）',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active' COMMENT '账号状态：disabled 即禁用，会话立即失效',
  avatar VARCHAR(255) NOT NULL DEFAULT '' COMMENT '头像文件相对站点根的路径，如 assets/avatars/av_xx.jpg；空 = 默认首字母头像',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
  PRIMARY KEY (id),
  UNIQUE KEY uk_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户表';

-- ---------- 动态/笔记表 ----------
CREATE TABLE IF NOT EXISTS posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED NOT NULL COMMENT '作者 ID',
  content TEXT NOT NULL COMMENT '正文（纯文本，输出时统一转义）',
  visibility ENUM('public','private') NOT NULL DEFAULT 'private' COMMENT '可见性：private 仅自己（默认）/ public 公开',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间（索引）',
  updated_at DATETIME NULL DEFAULT NULL COMMENT '最后编辑时间，NULL 表示从未编辑',
  PRIMARY KEY (id),
  KEY idx_posts_user_id (user_id),
  KEY idx_posts_created_at (created_at),
  KEY idx_posts_visibility (visibility),
  CONSTRAINT fk_posts_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='动态/笔记表';

-- ---------- 动态图片表 ----------
CREATE TABLE IF NOT EXISTS post_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL COMMENT '所属动态 ID',
  user_id INT UNSIGNED NOT NULL COMMENT '上传者 ID（冗余存储，便于归属校验）',
  file_path VARCHAR(255) NOT NULL COMMENT '相对 uploads/ 的路径，如 2026/09/xx_ab12cd34.jpg',
  original_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT '用户原始文件名（仅展示用，不参与存储命名）',
  mime_type VARCHAR(50) NOT NULL DEFAULT '' COMMENT '服务端校验出的真实 MIME',
  file_size INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件字节数',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  PRIMARY KEY (id),
  KEY idx_post_images_post_id (post_id),
  KEY idx_post_images_user_id (user_id),
  KEY idx_post_images_file_path (file_path),
  CONSTRAINT fk_post_images_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_post_images_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='动态图片表';

-- ---------- 动态附件表（视频 / 小文件；图片仍走 post_images） ----------
CREATE TABLE IF NOT EXISTS post_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  post_id BIGINT UNSIGNED NOT NULL COMMENT '所属动态 ID',
  user_id INT UNSIGNED NOT NULL COMMENT '上传者 ID（冗余存储，便于归属校验）',
  kind ENUM('video','file') NOT NULL DEFAULT 'file' COMMENT '附件类型：video 内联播放 / file 仅提供下载',
  original_name VARCHAR(255) NOT NULL DEFAULT '' COMMENT '用户原始文件名（仅展示与下载命名）',
  stored_name VARCHAR(255) NOT NULL COMMENT '随机存储文件名（含扩展名）',
  mime_type VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'finfo 校验出的真实 MIME',
  file_size INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '文件字节数',
  file_path VARCHAR(255) NOT NULL COMMENT '相对 uploads/ 的路径，如 att/2026/09/xx_ab12cd34.mp4',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  PRIMARY KEY (id),
  KEY idx_post_attachments_post_id (post_id),
  KEY idx_post_attachments_user_id (user_id),
  CONSTRAINT fk_post_attachments_post FOREIGN KEY (post_id) REFERENCES posts (id) ON DELETE CASCADE,
  CONSTRAINT fk_post_attachments_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='动态附件表（视频/文件）';

-- ---------- 邀请码表 ----------
CREATE TABLE IF NOT EXISTS invite_codes (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  code VARCHAR(32) NOT NULL COMMENT '邀请码，注册页唯一凭据',
  creator_id INT UNSIGNED NULL DEFAULT NULL COMMENT '创建人（管理员），删号后置空保留记录',
  max_uses SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '最大使用次数（1-999）',
  used_count SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已使用次数',
  expires_at DATETIME NULL DEFAULT NULL COMMENT '过期时间，NULL 表示永久',
  enabled TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 启用 / 0 停用',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (id),
  UNIQUE KEY uk_invite_code (code),
  KEY idx_invite_creator (creator_id),
  CONSTRAINT fk_invite_creator FOREIGN KEY (creator_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='注册邀请码';

-- ---------- 站点设置表 ----------
CREATE TABLE IF NOT EXISTS site_settings (
  setting_key VARCHAR(64) NOT NULL COMMENT '设置键',
  setting_value VARCHAR(255) NOT NULL DEFAULT '' COMMENT '设置值',
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站点设置（key-value）';

-- ---------- 初始设置 ----------
INSERT INTO site_settings (setting_key, setting_value) VALUES
  ('background_image', ''),
  ('guest_view_public', '0')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ---------- 可选：直接指定管理员 ----------
-- UPDATE users SET role = 'admin' WHERE username = '你的用户名';
