-- ============================================
-- CineGold Movie Website - Database Setup
-- Full System v2.0
-- ============================================

CREATE DATABASE IF NOT EXISTS cinegold_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE cinegold_db;

-- ==================== USERS ====================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT 'default.png',
    role ENUM('user','admin') DEFAULT 'user',
    is_active TINYINT(1) DEFAULT 1,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== GENRES ====================
CREATE TABLE IF NOT EXISTS genres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ==================== MOVIES ====================
CREATE TABLE IF NOT EXISTS movies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    poster VARCHAR(255),
    backdrop VARCHAR(255),
    trailer_url VARCHAR(500),
    release_year SMALLINT,
    imdb_rate DECIMAL(3,1) DEFAULT 0.0,
    quality ENUM('CAM','HD','FHD','4K') DEFAULT 'HD',
    duration VARCHAR(20),
    country VARCHAR(100),
    director VARCHAR(255),
    actors TEXT,
    type ENUM('movie','series') DEFAULT 'movie',
    status ENUM('published','draft') DEFAULT 'published',
    views INT DEFAULT 0,
    meta_title VARCHAR(255),
    meta_description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== MOVIE GENRES ====================
CREATE TABLE IF NOT EXISTS movie_genres (
    movie_id INT NOT NULL,
    genre_id INT NOT NULL,
    PRIMARY KEY (movie_id, genre_id),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== VIDEO LINKS ====================
CREATE TABLE IF NOT EXISTS video_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    season_number SMALLINT DEFAULT NULL,
    episode_number SMALLINT DEFAULT NULL,
    language ENUM('kurdish','arabic','english') NOT NULL,
    server_name VARCHAR(100) DEFAULT 'Server 1',
    video_url VARCHAR(500) NOT NULL,
    sort_order SMALLINT DEFAULT 0,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== SEASONS ====================
CREATE TABLE IF NOT EXISTS seasons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT NOT NULL,
    season_number SMALLINT NOT NULL,
    title VARCHAR(255),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_season (movie_id, season_number)
) ENGINE=InnoDB;

-- ==================== EPISODES ====================
CREATE TABLE IF NOT EXISTS episodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    season_id INT NOT NULL,
    episode_number SMALLINT NOT NULL,
    title VARCHAR(255),
    description TEXT,
    duration VARCHAR(20),
    poster VARCHAR(255),
    FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE,
    UNIQUE KEY unique_episode (season_id, episode_number)
) ENGINE=InnoDB;

-- ==================== FAVORITES ====================
CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fav (user_id, movie_id)
) ENGINE=InnoDB;

-- ==================== WATCH HISTORY ====================
CREATE TABLE IF NOT EXISTS watch_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== MOVIE REQUESTS ====================
CREATE TABLE IF NOT EXISTS movie_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    movie_name VARCHAR(255) NOT NULL,
    imdb_link VARCHAR(500),
    message TEXT,
    status ENUM('pending','approved','completed','rejected') DEFAULT 'pending',
    admin_reply TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==================== NOTIFICATIONS ====================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT,
    is_read TINYINT(1) DEFAULT 0,
    link VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== ADS ====================
CREATE TABLE IF NOT EXISTS ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255),
    type ENUM('banner','popup') DEFAULT 'banner',
    position ENUM('header','sidebar','footer','player','popup') DEFAULT 'sidebar',
    content TEXT NOT NULL,
    image_url VARCHAR(500),
    target_url VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== SECURITY LOGS ====================
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    user_agent TEXT,
    request_uri TEXT,
    attack_type VARCHAR(100),
    blocked TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== SITE SETTINGS ====================
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT
) ENGINE=InnoDB;

-- ==================== SLIDERS ====================
CREATE TABLE IF NOT EXISTS sliders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movie_id INT,
    title VARCHAR(255),
    description TEXT,
    image_url VARCHAR(500),
    link_url VARCHAR(500),
    sort_order SMALLINT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ==================== REPORTS (Broken Videos) ====================
CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    movie_id INT NOT NULL,
    video_link_id INT,
    reason TEXT,
    status ENUM('pending','resolved','dismissed') DEFAULT 'pending',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== COMMENTS ====================
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    movie_id INT NOT NULL,
    content TEXT NOT NULL,
    is_approved TINYINT(1) DEFAULT 1,
    is_spoiler TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== PASSWORD RESETS ====================
CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== SMTP SETTINGS ====================
CREATE TABLE IF NOT EXISTS smtp_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    smtp_host VARCHAR(255) DEFAULT '',
    smtp_port INT DEFAULT 587,
    smtp_email VARCHAR(255) DEFAULT '',
    smtp_password VARCHAR(255) DEFAULT '',
    smtp_from_name VARCHAR(255) DEFAULT 'CineGold',
    smtp_encryption ENUM('tls','ssl','none') DEFAULT 'tls',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO smtp_settings (smtp_host) VALUES ('');

-- ==================== WATCH PARTY ====================
CREATE TABLE IF NOT EXISTS watch_parties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(32) NOT NULL UNIQUE,
    movie_id INT NOT NULL,
    host_user_id INT NOT NULL,
    playback_time DECIMAL(10,2) DEFAULT 0,
    playback_status ENUM('playing','paused','stopped') DEFAULT 'paused',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (host_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS watch_party_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(32) NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS watch_party_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(32) NOT NULL,
    user_id INT NOT NULL,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_member (room_id, user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== FOLLOWS ====================
CREATE TABLE IF NOT EXISTS follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    following_id INT NOT NULL,
    status ENUM('active','pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (follower_id, following_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== USER PRIVACY ====================
CREATE TABLE IF NOT EXISTS user_privacy (
    user_id INT PRIMARY KEY,
    private_account TINYINT(1) DEFAULT 0,
    hide_from_search TINYINT(1) DEFAULT 0,
    disable_follow TINYINT(1) DEFAULT 0,
    hide_following TINYINT(1) DEFAULT 0,
    hide_points TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== WATCH PARTY INVITES ====================
CREATE TABLE IF NOT EXISTS watch_party_invites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(32) NOT NULL,
    inviter_id INT NOT NULL,
    invitee_id INT NOT NULL,
    status ENUM('pending','accepted','declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_invite (room_id, invitee_id)
) ENGINE=InnoDB;

-- ==================== IP BANS ====================
CREATE TABLE IF NOT EXISTS ip_bans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    ban_until DATETIME NOT NULL,
    reason VARCHAR(255) DEFAULT 'Auto-ban: suspicious activity',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ==================== USER POINTS ====================
CREATE TABLE IF NOT EXISTS user_points (
    user_id INT PRIMARY KEY,
    points INT DEFAULT 0,
    vip_until DATETIME DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== POINT ACTIVITIES ====================
CREATE TABLE IF NOT EXISTS point_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('watch','invite','comment','admin_adjust') NOT NULL,
    points_earned INT NOT NULL,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== DIRECT MESSAGES ====================
CREATE TABLE IF NOT EXISTS direct_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== DEFAULT DATA ====================
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('site_name', 'SHAH CINEMA'),
('site_description', 'بەهێزترین وێبسایتی فیلم و زنجیرە'),
('site_logo', 'logo.png'),
('favicon', 'favicon.ico'),
('contact_email', 'admin@shahcinema.com'),
('maintenance_mode', '0'),
('maintenance_message', 'ئێستا چاکسازیمان هەیە. بەمزووانە دەگەڕێینەوە!'),
('facebook_url', ''),
('twitter_url', ''),
('instagram_url', ''),
('telegram_url', ''),
('youtube_url', ''),
('points_watch', '5'),
('points_invite', '10'),
('points_comment', '2'),
('vip_threshold', '500');

INSERT IGNORE INTO genres (name, slug) VALUES
('Action', 'action'),
('Comedy', 'comedy'),
('Drama', 'drama'),
('Horror', 'horror'),
('Sci-Fi', 'sci-fi'),
('Romance', 'romance'),
('Thriller', 'thriller'),
('Animation', 'animation'),
('Documentary', 'documentary'),
('Adventure', 'adventure'),
('Fantasy', 'fantasy'),
('Crime', 'crime');

-- Default admin: admin / admin123
INSERT IGNORE INTO users (username, email, password, role) VALUES
('admin', 'admin@shahcinema.com', '$2y$10$E1Kz6YGMiY5WCkBvXALmNOQpGxVqfE6UJyOeQ3tvHKMJy5gN2XLHK', 'admin');

-- Sample movie
INSERT IGNORE INTO movies (title, slug, description, release_year, imdb_rate, quality, duration, country, director, type) VALUES
('The Dark Knight', 'the-dark-knight', 'When the menace known as the Joker wreaks havoc on Gotham, Batman must accept one of the greatest tests.', 2008, 9.0, 'FHD', '152 min', 'USA', 'Christopher Nolan', 'movie');

-- ==================== ACTORS ====================
CREATE TABLE IF NOT EXISTS actors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tmdb_id INT DEFAULT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    name_ku VARCHAR(255),
    slug VARCHAR(255) NOT NULL,
    biography TEXT,
    biography_ku TEXT,
    birthday DATE DEFAULT NULL,
    deathday DATE DEFAULT NULL,
    place_of_birth VARCHAR(255),
    profile_image VARCHAR(255),
    known_for VARCHAR(100) DEFAULT 'Acting',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_slug (slug)
) ENGINE=InnoDB;

-- ==================== MOVIE ACTORS ====================
CREATE TABLE IF NOT EXISTS movie_actors (
    movie_id INT NOT NULL,
    actor_id INT NOT NULL,
    role_name VARCHAR(255) DEFAULT '',
    sort_order SMALLINT DEFAULT 0,
    PRIMARY KEY (movie_id, actor_id),
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES actors(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== USER BLOCKS ====================
CREATE TABLE IF NOT EXISTS user_blocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blocker_id INT NOT NULL,
    blocked_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_block (blocker_id, blocked_id),
    FOREIGN KEY (blocker_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (blocked_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== USER REPORTS ====================
CREATE TABLE IF NOT EXISTS user_reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT NOT NULL,
    reported_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','reviewed','dismissed') DEFAULT 'pending',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== STAFF ====================
CREATE TABLE IF NOT EXISTS staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    role_title VARCHAR(255) NOT NULL,
    bio TEXT,
    avatar VARCHAR(255),
    sort_order SMALLINT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Add bio & cinema_mood columns to users
ALTER TABLE users ADD COLUMN IF NOT EXISTS bio TEXT DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS cinema_mood VARCHAR(255) DEFAULT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS premium_emoji VARCHAR(50) DEFAULT NULL;

-- ==================== STORIES ====================
CREATE TABLE IF NOT EXISTS stories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    media_type ENUM('image','video') DEFAULT 'image',
    media_file VARCHAR(255) NOT NULL,
    caption VARCHAR(500) DEFAULT '',
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_expires (expires_at),
    INDEX idx_user (user_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS story_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    story_id INT NOT NULL,
    viewer_id INT NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_view (story_id, viewer_id),
    FOREIGN KEY (story_id) REFERENCES stories(id) ON DELETE CASCADE,
    FOREIGN KEY (viewer_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== CHAT REQUESTS ====================
CREATE TABLE IF NOT EXISTS chat_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request (sender_id, receiver_id),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== USER MUTES ====================
CREATE TABLE IF NOT EXISTS user_mutes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    muted_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_mute (user_id, muted_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (muted_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ==================== ROOM REACTIONS ====================
CREATE TABLE IF NOT EXISTS room_reactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(32) NOT NULL,
    user_id INT NOT NULL,
    emoji VARCHAR(10) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_time (room_id, created_at)
) ENGINE=InnoDB;

-- Additional settings
INSERT IGNORE INTO settings (setting_key, setting_value) VALUES
('tmdb_api_key', ''),
('footer_text', 'هەموو مافێکی پارێزراوە'),
('copyright_text', '© 2026 SHAH CINEMA - هەموو مافێک پارێزراوە'),
('loader_logo', '');
