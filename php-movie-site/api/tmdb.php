<?php
/**
 * TMDB API Integration - Actor Auto-Fetch & Kurdish Translation
 * Enhanced: Full Kurdish biography translation via Google Translate fallback
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'login_required']);
    exit;
}

// TMDB API Key from settings
$tmdbKey = '';
try {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'tmdb_api_key'");
    $stmt->execute();
    $tmdbKey = $stmt->fetchColumn() ?: '';
} catch (Exception $e) {}

if (!$tmdbKey) {
    echo json_encode(['success' => false, 'message' => 'TMDB API Key not configured. Set it in Admin > Settings.']);
    exit;
}

$action = $_GET['action'] ?? '';
$data = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $action ?: ($data['action'] ?? '');

switch ($action) {

    case 'search_actor':
        $query = clean($_GET['q'] ?? '');
        if (strlen($query) < 2) {
            echo json_encode(['success' => false, 'message' => 'Query too short']);
            exit;
        }
        
        $url = "https://api.themoviedb.org/3/search/person?api_key={$tmdbKey}&query=" . urlencode($query) . "&language=en-US&page=1";
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        $results = [];
        if (!empty($data['results'])) {
            foreach (array_slice($data['results'], 0, 10) as $person) {
                $results[] = [
                    'tmdb_id' => $person['id'],
                    'name' => $person['name'],
                    'profile_path' => $person['profile_path'] ? 'https://image.tmdb.org/t/p/w185' . $person['profile_path'] : null,
                    'known_for_department' => $person['known_for_department'] ?? 'Acting',
                ];
            }
        }
        echo json_encode(['success' => true, 'results' => $results]);
        break;

    case 'get_actor':
        $tmdbId = (int)($_GET['tmdb_id'] ?? 0);
        if (!$tmdbId) {
            echo json_encode(['success' => false, 'message' => 'Missing TMDB ID']);
            exit;
        }
        
        $url = "https://api.themoviedb.org/3/person/{$tmdbId}?api_key={$tmdbKey}&language=en-US";
        $response = @file_get_contents($url);
        if (!$response) {
            echo json_encode(['success' => false, 'message' => 'TMDB API error']);
            exit;
        }
        $person = json_decode($response, true);
        
        // Full Kurdish translation
        $biography = $person['biography'] ?? '';
        $biographyKu = translateToKurdish($biography);
        
        $result = [
            'tmdb_id' => $person['id'],
            'name' => $person['name'],
            'biography' => $biography,
            'biography_ku' => $biographyKu,
            'birthday' => $person['birthday'] ?? null,
            'deathday' => $person['deathday'] ?? null,
            'place_of_birth' => $person['place_of_birth'] ?? null,
            'profile_path' => $person['profile_path'] ? 'https://image.tmdb.org/t/p/w500' . $person['profile_path'] : null,
            'known_for_department' => $person['known_for_department'] ?? 'Acting',
            'popularity' => $person['popularity'] ?? 0,
        ];
        
        echo json_encode(['success' => true, 'actor' => $result]);
        break;

    case 'save_actor':
        if (!isAdmin()) {
            echo json_encode(['success' => false, 'message' => 'Admin only']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $tmdbId = (int)($data['tmdb_id'] ?? 0);
        $name = clean($data['name'] ?? '');
        $nameKu = clean($data['name_ku'] ?? '');
        $biography = $data['biography'] ?? '';
        $biographyKu = $data['biography_ku'] ?? '';
        $birthday = $data['birthday'] ?? null;
        $deathday = $data['deathday'] ?? null;
        $placeOfBirth = clean($data['place_of_birth'] ?? '');
        $profileUrl = clean($data['profile_url'] ?? '');
        $knownFor = clean($data['known_for'] ?? 'Acting');
        
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Name required']);
            exit;
        }
        
        // Download profile image
        $profileFile = '';
        if ($profileUrl && filter_var($profileUrl, FILTER_VALIDATE_URL)) {
            $imgData = @file_get_contents($profileUrl);
            if ($imgData) {
                $ext = 'jpg';
                $profileFile = 'actor_' . bin2hex(random_bytes(8)) . '.' . $ext;
                $dir = __DIR__ . '/../uploads/actors';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($dir . '/' . $profileFile, $imgData);
            }
        }
        
        $slug = createSlug($name);
        try {
            $stmt = $pdo->prepare("INSERT INTO actors (tmdb_id, name, name_ku, slug, biography, biography_ku, birthday, deathday, place_of_birth, profile_image, known_for) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE name_ku=VALUES(name_ku), biography=VALUES(biography), biography_ku=VALUES(biography_ku), birthday=VALUES(birthday), deathday=VALUES(deathday), place_of_birth=VALUES(place_of_birth), profile_image=VALUES(profile_image), known_for=VALUES(known_for)");
            $stmt->execute([$tmdbId, $name, $nameKu ?: $name, $slug, $biography, $biographyKu, $birthday, $deathday, $placeOfBirth, $profileFile, $knownFor]);
            
            $actorId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM actors WHERE tmdb_id = $tmdbId")->fetchColumn();
            echo json_encode(['success' => true, 'actor_id' => $actorId]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    case 'link_movie':
        if (!isAdmin()) { echo json_encode(['success' => false]); exit; }
        $data = json_decode(file_get_contents('php://input'), true);
        $actorId = (int)($data['actor_id'] ?? 0);
        $movieId = (int)($data['movie_id'] ?? 0);
        $role = clean($data['role'] ?? '');
        
        if (!$actorId || !$movieId) {
            echo json_encode(['success' => false, 'message' => 'Missing data']);
            exit;
        }
        
        try {
            $pdo->prepare("INSERT IGNORE INTO movie_actors (movie_id, actor_id, role_name) VALUES (?, ?, ?)")
                ->execute([$movieId, $actorId, $role]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Comprehensive English-to-Kurdish translation for actor biographies
 */
function translateToKurdish(string $text): string {
    if (empty($text)) return '';
    
    // Try Google Translate free API first
    $translated = googleTranslateToKurdish($text);
    if ($translated && $translated !== $text) {
        return $translated;
    }
    
    // Fallback: comprehensive phrase replacement
    $replacements = [
        // Identity phrases
        'is an American' => 'ئەکتەرێکی ئەمریکییە',
        'is a British' => 'ئەکتەرێکی بەریتانییە',
        'is an English' => 'ئەکتەرێکی ئینگلیزییە',
        'is a Canadian' => 'ئەکتەرێکی کەنەدییە',
        'is an Australian' => 'ئەکتەرێکی ئوسترالییە',
        'is a South Korean' => 'ئەکتەرێکی کۆریای باشوورییە',
        'is an Indian' => 'ئەکتەرێکی هیندییە',
        'is a French' => 'ئەکتەرێکی فەڕەنسییە',
        'is a German' => 'ئەکتەرێکی ئەڵمانییە',
        'is a Spanish' => 'ئەکتەرێکی ئیسپانییە',
        'is an Irish' => 'ئەکتەرێکی ئێرلەندییە',
        'is a Scottish' => 'ئەکتەرێکی سکۆتلەندییە',
        'is a Welsh' => 'ئەکتەرێکی وەیڵزییە',
        'is a Swedish' => 'ئەکتەرێکی سویدییە',
        'is a Danish' => 'ئەکتەرێکی دانمارکییە',
        'is a Norwegian' => 'ئەکتەرێکی نەرویجییە',
        'is a Mexican' => 'ئەکتەرێکی مەکسیکییە',
        'is a Japanese' => 'ئەکتەرێکی ژاپۆنییە',
        'is a Chinese' => 'ئەکتەرێکی چینییە',
        'is a Turkish' => 'ئەکتەرێکی تورکییە',
        'is an Italian' => 'ئەکتەرێکی ئیتالییە',
        
        // Professions
        'actor and director' => 'ئەکتەر و دەرهێنەر',
        'actress and director' => 'ئەکتەر و دەرهێنەر',
        'actor and producer' => 'ئەکتەر و بەرهەمهێنەر',
        'actress and producer' => 'ئەکتەر و بەرهەمهێنەر',
        'actor, director, and producer' => 'ئەکتەر، دەرهێنەر و بەرهەمهێنەر',
        'actor' => 'ئەکتەر',
        'actress' => 'ئەکتەر',
        'director' => 'دەرهێنەر',
        'producer' => 'بەرهەمهێنەر',
        'writer' => 'نووسەر',
        'screenwriter' => 'سیناریۆنووس',
        'filmmaker' => 'فیلمساز',
        'singer' => 'گۆرانیبێژ',
        'musician' => 'میوزیکژەن',
        'comedian' => 'کۆمیدیاکار',
        'model' => 'مۆدێل',
        'voice actor' => 'دەنگداری ئەکتەر',
        'stunt performer' => 'ستانتمەن',
        'choreographer' => 'کۆریۆگرافەر',
        'cinematographer' => 'سینەماتۆگرافەر',
        
        // Common biography phrases
        'was born on' => 'لە بەرواری',
        'was born in' => 'لە',
        'born on' => 'لەدایکبووە لە بەرواری',
        'born' => 'لەدایکبووە',
        'He is known for' => 'ناسراوە بۆ',
        'She is known for' => 'ناسراوە بۆ',
        'They are known for' => 'ناسراون بۆ',
        'He is best known for' => 'زۆرتر ناسراوە بۆ',
        'She is best known for' => 'زۆرتر ناسراوە بۆ',
        'He has appeared in' => 'دەرکەوتووە لە',
        'She has appeared in' => 'دەرکەوتووە لە',
        'He starred in' => 'ئەکتەری سەرەکی بوو لە',
        'She starred in' => 'ئەکتەری سەرەکی بوو لە',
        'He began his career' => 'کاری خۆی دەستپێکرد',
        'She began her career' => 'کاری خۆی دەستپێکرد',
        'He started his career' => 'کاری خۆی دەستپێکرد',
        'She started her career' => 'کاری خۆی دەستپێکرد',
        'His career' => 'کاری',
        'Her career' => 'کاری',
        'He received' => 'وەرگرت',
        'She received' => 'وەرگرت',
        'He won' => 'بردییەوە',
        'She won' => 'بردییەوە',
        'He was nominated' => 'کاندید کرا',
        'She was nominated' => 'کاندید کرا',
        'He has won' => 'بردییەوە',
        'She has won' => 'بردییەوە',
        'He also' => 'هەروەها',
        'She also' => 'هەروەها',
        'In addition to' => 'لەگەڵ',
        'Throughout his career' => 'لە درێژایی کاریدا',
        'Throughout her career' => 'لە درێژایی کاریدا',
        
        // Awards
        'Academy Award' => 'خەڵاتی ئۆسکار',
        'Academy Awards' => 'خەڵاتەکانی ئۆسکار',
        'Oscar' => 'ئۆسکار',
        'Golden Globe' => 'گلۆبی زێڕین',
        'Golden Globe Award' => 'خەڵاتی گلۆبی زێڕین',
        'Emmy Award' => 'خەڵاتی ئیمی',
        'BAFTA' => 'بافتا',
        'Screen Actors Guild' => 'یەکیەتی ئەکتەرانی پەردە',
        'SAG Award' => 'خەڵاتی SAG',
        'Cannes Film Festival' => 'فیستیڤاڵی فیلمی کان',
        'Sundance' => 'ساندانس',
        'Tony Award' => 'خەڵاتی تۆنی',
        'Grammy Award' => 'خەڵاتی گرامی',
        'Palme d\'Or' => 'پالمی زێڕین',
        'Best Actor' => 'باشترین ئەکتەر',
        'Best Actress' => 'باشترین ئەکتەر',
        'Best Supporting Actor' => 'باشترین ئەکتەری یاریدەدەر',
        'Best Supporting Actress' => 'باشترین ئەکتەری یاریدەدەر',
        'Best Director' => 'باشترین دەرهێنەر',
        'Best Picture' => 'باشترین فیلم',
        'nomination' => 'کاندیدبوون',
        'nominations' => 'کاندیدبوونەکان',
        'nominated' => 'کاندید کرا',
        'winner' => 'بردنەوە',
        'award' => 'خەڵات',
        'awards' => 'خەڵاتەکان',
        
        // Film/TV terms
        'film' => 'فیلم',
        'films' => 'فیلمەکان',
        'movie' => 'فیلم',
        'movies' => 'فیلمەکان',
        'television' => 'تەلەڤیزیۆن',
        'TV series' => 'زنجیرەی تەلەڤیزیۆنی',
        'TV show' => 'بەرنامەی تەلەڤیزیۆنی',
        'blockbuster' => 'فیلمی بەناوبانگ',
        'box office' => 'فرۆشی بلیت',
        'sequel' => 'بەردەوامی',
        'franchise' => 'فرانچایز',
        'thriller' => 'ڤێسەبەر',
        'comedy' => 'کۆمیدی',
        'drama' => 'دراما',
        'action' => 'ئاکشن',
        'horror' => 'ترسناک',
        'romance' => 'ڕۆمانسی',
        'science fiction' => 'زانستی خەیاڵی',
        'sci-fi' => 'سای-فای',
        'animated' => 'ئەنیمەیشن',
        'documentary' => 'دۆکیومێنتاری',
        'superhero' => 'سوپەرهیرۆ',
        
        // Time/age
        'years old' => 'ساڵ',
        'at the age of' => 'لە تەمەنی',
        'early career' => 'سەرەتای کارەکەی',
        'childhood' => 'منداڵیی',
        'debut' => 'یەکەم ڕۆڵ',
        'retired' => 'خانەنشین بوو',
        'died' => 'کۆچی دوایی کرد',
        'passed away' => 'کۆچی دوایی کرد',
        
        // Misc
        'and' => 'و',
        'the' => '',
        'of' => 'ی',
        'in' => 'لە',
        'for' => 'بۆ',
        'with' => 'لەگەڵ',
        'from' => 'لە',
        'by' => 'لەلایەن',
        'including' => 'لەنێوان',
        'such as' => 'وەک',
        'as well as' => 'هەروەها',
        'career' => 'کار',
        'role' => 'ڕۆڵ',
        'roles' => 'ڕۆڵەکان',
        'performance' => 'ئەداکاری',
        'critically acclaimed' => 'ستایشکراو',
        'worldwide' => 'جیهانی',
        'international' => 'نێودەوڵەتی',
        'successful' => 'سەرکەوتوو',
        'famous' => 'بەناوبانگ',
        'popular' => 'ناسراو',
        'known' => 'ناسراو',
        'starred' => 'ئەکتەری سەرەکی بوو',
        'appeared' => 'دەرکەوت',
        'featured' => 'تایبەتمەندکرا',
        'portrayed' => 'ڕۆڵی ئەدا کرد',
        'played' => 'ئەدای کرد',
        'character' => 'کەسایەتی',
        'characters' => 'کەسایەتییەکان',
        'University' => 'زانکۆ',
        'School' => 'قوتابخانە',
        'College' => 'کۆلێج',
        'family' => 'خێزان',
        'married' => 'هاوسەرگیری کرد',
        'divorced' => 'جیابوونەوە',
        'children' => 'منداڵەکان',
    ];
    
    $translated = $text;
    // Sort by longest key first to avoid partial replacements
    uksort($replacements, function($a, $b) { return strlen($b) - strlen($a); });
    
    foreach ($replacements as $en => $ku) {
        $translated = str_ireplace($en, $ku, $translated);
    }
    
    return $translated;
}

/**
 * Free Google Translate API (no key needed, limited)
 */
function googleTranslateToKurdish(string $text): string {
    if (empty($text) || strlen($text) < 10) return '';
    
    // Limit text length for free API
    $text = mb_substr($text, 0, 2000);
    
    $url = 'https://translate.googleapis.com/translate_a/single?client=gtx'
        . '&sl=en&tl=ku&dt=t&q=' . urlencode($text);
    
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'header' => 'User-Agent: Mozilla/5.0'
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    if (!$response) return '';
    
    $data = json_decode($response, true);
    if (!$data || !isset($data[0])) return '';
    
    $result = '';
    foreach ($data[0] as $segment) {
        if (isset($segment[0])) {
            $result .= $segment[0];
        }
    }
    
    return $result;
}
