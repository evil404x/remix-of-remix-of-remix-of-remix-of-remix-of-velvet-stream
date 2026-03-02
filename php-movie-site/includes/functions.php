<?php
/**
 * CineGold - Helper Functions
 * Clean, modern, comprehensive
 */

// Get all movies with pagination
function getMovies(PDO $pdo, int $page = 1, int $limit = 20, string $type = ''): array {
    $offset = ($page - 1) * $limit;
    $where = "WHERE m.status = 'published'";
    $params = [];
    
    if ($type) {
        $where .= " AND m.type = ?";
        $params[] = $type;
    }
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare("SELECT m.*, GROUP_CONCAT(g.name) as genres 
        FROM movies m 
        LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
        LEFT JOIN genres g ON mg.genre_id = g.id 
        $where 
        GROUP BY m.id 
        ORDER BY m.created_at DESC 
        LIMIT ? OFFSET ?");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get single movie by slug
function getMovie(PDO $pdo, string $slug): ?array {
    $stmt = $pdo->prepare("SELECT m.*, GROUP_CONCAT(g.name) as genres 
        FROM movies m 
        LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
        LEFT JOIN genres g ON mg.genre_id = g.id 
        WHERE m.slug = ? 
        GROUP BY m.id");
    $stmt->execute([$slug]);
    $movie = $stmt->fetch();
    return $movie ?: null;
}

// Get video links for a movie/episode
function getVideoLinks(PDO $pdo, int $movieId, ?int $season = null, ?int $episode = null): array {
    $sql = "SELECT * FROM video_links WHERE movie_id = ?";
    $params = [$movieId];
    
    if ($season !== null) {
        $sql .= " AND season_number = ?";
        $params[] = $season;
    }
    if ($episode !== null) {
        $sql .= " AND episode_number = ?";
        $params[] = $episode;
    }
    
    $sql .= " ORDER BY language, sort_order";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get seasons for a series
function getSeasons(PDO $pdo, int $movieId): array {
    $stmt = $pdo->prepare("SELECT * FROM seasons WHERE movie_id = ? ORDER BY season_number");
    $stmt->execute([$movieId]);
    return $stmt->fetchAll();
}

// Get episodes for a season
function getEpisodes(PDO $pdo, int $seasonId): array {
    $stmt = $pdo->prepare("SELECT * FROM episodes WHERE season_id = ? ORDER BY episode_number");
    $stmt->execute([$seasonId]);
    return $stmt->fetchAll();
}

// Search movies with filters
function searchMovies(PDO $pdo, array $filters): array {
    $where = ["m.status = 'published'"];
    $params = [];
    
    if (!empty($filters['q'])) {
        $where[] = "(m.title LIKE ? OR m.description LIKE ?)";
        $params[] = '%' . $filters['q'] . '%';
        $params[] = '%' . $filters['q'] . '%';
    }
    if (!empty($filters['type'])) {
        $where[] = "m.type = ?";
        $params[] = $filters['type'];
    }
    if (!empty($filters['genre'])) {
        $where[] = "g.slug = ?";
        $params[] = $filters['genre'];
    }
    if (!empty($filters['year'])) {
        $where[] = "m.release_year = ?";
        $params[] = (int)$filters['year'];
    }
    if (!empty($filters['quality'])) {
        $where[] = "m.quality = ?";
        $params[] = $filters['quality'];
    }
    if (!empty($filters['imdb_min'])) {
        $where[] = "m.imdb_rate >= ?";
        $params[] = (float)$filters['imdb_min'];
    }
    
    $orderBy = match($filters['sort'] ?? 'newest') {
        'imdb' => 'm.imdb_rate DESC',
        'views' => 'm.views DESC',
        'year' => 'm.release_year DESC',
        'oldest' => 'm.created_at ASC',
        default => 'm.created_at DESC'
    };
    
    $sql = "SELECT DISTINCT m.*, GROUP_CONCAT(DISTINCT g.name) as genres 
            FROM movies m 
            LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
            LEFT JOIN genres g ON mg.genre_id = g.id 
            WHERE " . implode(' AND ', $where) . "
            GROUP BY m.id 
            ORDER BY $orderBy 
            LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Toggle favorite
function toggleFavorite(PDO $pdo, int $userId, int $movieId): bool {
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
    $stmt->execute([$userId, $movieId]);
    
    if ($stmt->fetch()) {
        $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND movie_id = ?")->execute([$userId, $movieId]);
        return false;
    } else {
        $pdo->prepare("INSERT INTO favorites (user_id, movie_id) VALUES (?, ?)")->execute([$userId, $movieId]);
        return true;
    }
}

// Check if favorited
function isFavorited(PDO $pdo, int $userId, int $movieId): bool {
    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND movie_id = ?");
    $stmt->execute([$userId, $movieId]);
    return (bool)$stmt->fetch();
}

// Add to watch history
function addToHistory(PDO $pdo, int $userId, int $movieId): void {
    $pdo->prepare("INSERT INTO watch_history (user_id, movie_id) VALUES (?, ?) ON DUPLICATE KEY UPDATE watched_at = CURRENT_TIMESTAMP")->execute([$userId, $movieId]);
}

// Increment view count
function incrementViews(PDO $pdo, int $movieId): void {
    $pdo->prepare("UPDATE movies SET views = views + 1 WHERE id = ?")->execute([$movieId]);
}

// Get active ads by position
function getAds(PDO $pdo, string $position): array {
    $stmt = $pdo->prepare("SELECT * FROM ads WHERE position = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE())");
    $stmt->execute([$position]);
    return $stmt->fetchAll();
}

// Get slider items
function getSliders(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT s.*, m.slug, m.title as movie_title, m.imdb_rate, m.release_year, m.quality, m.description as movie_desc, m.backdrop, m.genres 
            FROM sliders s 
            LEFT JOIN movies m ON s.movie_id = m.id 
            WHERE s.is_active = 1 
            ORDER BY s.sort_order ASC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Generate SEO meta tags
function generateMeta(array $movie): string {
    $title = clean($movie['meta_title'] ?: $movie['title'] . ' | ' . SITE_NAME);
    $desc = clean($movie['meta_description'] ?: mb_substr($movie['description'] ?? '', 0, 160));
    $poster = SITE_URL . '/uploads/posters/' . ($movie['poster'] ?? 'default.jpg');
    $url = SITE_URL . '/movie/' . $movie['slug'];
    
    $schema = json_encode([
        "@context" => "https://schema.org",
        "@type" => "Movie",
        "name" => $movie['title'],
        "description" => $movie['description'] ?? '',
        "image" => $poster,
        "datePublished" => $movie['release_year'] ?? '',
        "aggregateRating" => [
            "@type" => "AggregateRating",
            "ratingValue" => $movie['imdb_rate'] ?? 0,
            "bestRating" => 10,
            "ratingCount" => $movie['views'] ?? 1
        ]
    ], JSON_UNESCAPED_UNICODE);
    
    return <<<HTML
    <title>$title</title>
    <meta name="description" content="$desc">
    <meta property="og:title" content="$title">
    <meta property="og:description" content="$desc">
    <meta property="og:image" content="$poster">
    <meta property="og:url" content="$url">
    <meta property="og:type" content="video.movie">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="$url">
    <script type="application/ld+json">$schema</script>
    HTML;
}

// Create URL-friendly slug
function createSlug(string $text): string {
    $text = preg_replace('/[^a-zA-Z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return strtolower(trim($text, '-'));
}

// Send notification
function sendNotification(PDO $pdo, int $userId, string $title, string $message, string $link = ''): void {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $link]);
}

// Get notifications
function getNotifications(PDO $pdo, int $userId, int $limit = 10): array {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

// Get unread count
function getUnreadCount(PDO $pdo, int $userId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

// Dashboard stats
function getDashboardStats(PDO $pdo): array {
    return [
        'total_movies' => $pdo->query("SELECT COUNT(*) FROM movies WHERE type='movie'")->fetchColumn(),
        'total_series' => $pdo->query("SELECT COUNT(*) FROM movies WHERE type='series'")->fetchColumn(),
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'total_views' => $pdo->query("SELECT SUM(views) FROM movies")->fetchColumn() ?: 0,
        'pending_requests' => $pdo->query("SELECT COUNT(*) FROM movie_requests WHERE status='pending'")->fetchColumn(),
        'recent_attacks' => $pdo->query("SELECT COUNT(*) FROM security_logs WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn(),
        'total_reports' => (function() use ($pdo) {
            try { return $pdo->query("SELECT COUNT(*) FROM reports WHERE status='pending'")->fetchColumn(); } 
            catch (Exception $e) { return 0; }
        })(),
    ];
}

// Get all genres
function getGenres(PDO $pdo): array {
    return $pdo->query("SELECT * FROM genres ORDER BY name")->fetchAll();
}

// Get suggested movies
function getSuggestedMovies(PDO $pdo, array $movie, int $limit = 6): array {
    $stmt = $pdo->prepare("SELECT DISTINCT m.*, GROUP_CONCAT(g.name) as genres 
        FROM movies m 
        LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
        LEFT JOIN genres g ON mg.genre_id = g.id 
        WHERE m.id != ? AND m.status = 'published' AND m.type = ?
        GROUP BY m.id 
        ORDER BY RAND() 
        LIMIT ?");
    $stmt->execute([$movie['id'], $movie['type'], $limit]);
    return $stmt->fetchAll();
}

// Get suggested movies by same genre
function getSuggestedMoviesByGenre(PDO $pdo, array $movie, int $limit = 6): array {
    // Get this movie's genre IDs
    try {
        $genreStmt = $pdo->prepare("SELECT genre_id FROM movie_genres WHERE movie_id = ?");
        $genreStmt->execute([$movie['id']]);
        $genreIds = $genreStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($genreIds)) {
            $placeholders = implode(',', array_fill(0, count($genreIds), '?'));
            $params = array_merge($genreIds, [$movie['id'], $limit]);
            $stmt = $pdo->prepare("SELECT DISTINCT m.*, GROUP_CONCAT(DISTINCT g.name) as genres 
                FROM movies m 
                JOIN movie_genres mg ON m.id = mg.movie_id 
                LEFT JOIN genres g ON mg.genre_id = g.id 
                WHERE mg.genre_id IN ($placeholders) AND m.id != ? AND m.status = 'published'
                GROUP BY m.id 
                ORDER BY RAND() 
                LIMIT ?");
            $stmt->execute($params);
            $results = $stmt->fetchAll();
            if (!empty($results)) return $results;
        }
    } catch (Exception $e) {}
    
    // Fallback to same type
    return getSuggestedMovies($pdo, $movie, $limit);
}

// Get all users (admin)
function getUsers(PDO $pdo): array {
    return $pdo->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();
}

// Get reports
function getReports(PDO $pdo): array {
    try {
        $stmt = $pdo->query("SELECT r.*, m.title as movie_title, u.username 
            FROM reports r 
            LEFT JOIN movies m ON r.movie_id = m.id 
            LEFT JOIN users u ON r.user_id = u.id 
            ORDER BY r.created_at DESC");
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// Get movie requests (admin)
function getRequests(PDO $pdo): array {
    $stmt = $pdo->query("SELECT mr.*, u.username FROM movie_requests mr LEFT JOIN users u ON mr.user_id = u.id ORDER BY mr.created_at DESC");
    return $stmt->fetchAll();
}

// ============ LINK ENCRYPTION ============
function encryptVideoUrl(string $url): string {
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $iv = random_bytes(16);
    $encrypted = openssl_encrypt($url, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

function decryptVideoUrl(string $token): ?string {
    $data = base64_decode($token);
    if (!$data || strpos($data, '::') === false) return null;
    [$iv, $encrypted] = explode('::', $data, 2);
    $key = hash('sha256', ENCRYPTION_KEY, true);
    $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    return $decrypted ?: null;
}

// Get encrypted video links
function getEncryptedVideoLinks(PDO $pdo, int $movieId, ?int $season = null, ?int $episode = null): array {
    $links = getVideoLinks($pdo, $movieId, $season, $episode);
    foreach ($links as &$link) {
        $link['encrypted_url'] = encryptVideoUrl($link['video_url']);
        unset($link['video_url']); // Don't expose raw URL
    }
    return $links;
}

// ============ ACTOR FUNCTIONS ============
function getActorMovies(PDO $pdo, string $actorName, int $limit = 20): array {
    $stmt = $pdo->prepare("SELECT m.*, GROUP_CONCAT(g.name) as genres 
        FROM movies m 
        LEFT JOIN movie_genres mg ON m.id = mg.movie_id 
        LEFT JOIN genres g ON mg.genre_id = g.id 
        WHERE m.actors LIKE ? AND m.status = 'published'
        GROUP BY m.id 
        ORDER BY m.release_year DESC
        LIMIT ?");
    $stmt->execute(['%' . $actorName . '%', $limit]);
    return $stmt->fetchAll();
}

function parseActors(string $actorsStr): array {
    $actors = preg_split('/[,،\n]+/', $actorsStr);
    return array_filter(array_map('trim', $actors));
}

// ============ ENHANCED SEO ============
function generateMovieSchema(array $movie): string {
    $poster = SITE_URL . '/uploads/posters/' . ($movie['poster'] ?? 'default.jpg');
    $url = SITE_URL . '/movie/' . $movie['slug'];
    
    $schema = [
        "@context" => "https://schema.org",
        "@type" => $movie['type'] === 'series' ? "TVSeries" : "Movie",
        "name" => $movie['title'],
        "description" => $movie['description'] ?? '',
        "image" => $poster,
        "url" => $url,
        "datePublished" => (string)($movie['release_year'] ?? ''),
        "duration" => $movie['duration'] ?? '',
        "contentRating" => $movie['quality'] ?? 'HD',
        "inLanguage" => ["ku", "ar", "en"],
    ];
    
    if (!empty($movie['director'])) {
        $schema['director'] = [
            "@type" => "Person",
            "name" => $movie['director']
        ];
    }
    
    if (!empty($movie['actors'])) {
        $actors = parseActors($movie['actors']);
        $schema['actor'] = array_map(fn($a) => ["@type" => "Person", "name" => $a], $actors);
    }
    
    if ($movie['imdb_rate'] > 0) {
        $schema['aggregateRating'] = [
            "@type" => "AggregateRating",
            "ratingValue" => $movie['imdb_rate'],
            "bestRating" => 10,
            "worstRating" => 0,
            "ratingCount" => max(1, $movie['views'] ?? 1)
        ];
    }
    
    if (!empty($movie['country'])) {
        $schema['countryOfOrigin'] = [
            "@type" => "Country",
            "name" => $movie['country']
        ];
    }
    
    return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}
