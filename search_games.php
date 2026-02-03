<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: text/html; charset=utf-8');
session_start();

$limit  = max(1, min(50, (int)($_GET['limit'] ?? 20)));
$offset = max(0, (int)($_GET['offset'] ?? 0));
$q      = trim($_GET['q'] ?? '');

$params = [];
$sql = "SELECT * FROM games";
if ($q !== '') {
    $sql .= " WHERE name LIKE :q";
    $params[':q'] = "%$q%";
}
$sql .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";
$params[':limit']  = $limit;
$params[':offset'] = $offset;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Returns a relative path to a locally stored image for a given non-Steam game ID.
 */
function findLocalGameImage(int $gameId): ?string
{
    static $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    $baseDir = __DIR__ . '/images';

    foreach ($extensions as $ext) {
        $fileName = $gameId . '.' . $ext;
        if (is_file($baseDir . '/' . $fileName)) {
            return 'images/' . $fileName;
        }
    }

    return null;
}

$ids = array_column($games, 'id');
$voteAgg   = [];
$userVotes = [];
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmtAgg = $pdo->prepare(
        "SELECT game_id, SUM(vote_type='kbm') AS kbm_count, SUM(vote_type='controller') AS controller_count
           FROM votes
          WHERE game_id IN ($placeholders)
          GROUP BY game_id"
    );
    $stmtAgg->execute($ids);
    foreach ($stmtAgg->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $voteAgg[$row['game_id']] = [
            'kbm'        => (int)$row['kbm_count'],
            'controller' => (int)$row['controller_count']
        ];
    }
    $userId = $_SESSION['user_id'] ?? null;
    if ($userId) {
        $stmtVotes = $pdo->prepare(
            "SELECT game_id, vote_type FROM votes WHERE user_id = ? AND game_id IN ($placeholders)"
        );
        $stmtVotes->execute(array_merge([$userId], $ids));
        $userVotes = $stmtVotes->fetchAll(PDO::FETCH_KEY_PAIR);
    }
}

foreach ($games as $game) {
    $gid     = (int)$game['id'];
    $isSteam = isset($game['is_steam']) ? (int)$game['is_steam'] : 1;
    $counts  = $voteAgg[$gid] ?? ['kbm'=>0,'controller'=>0];
    $your    = $userVotes[$gid] ?? null;

    $majorIcon = 'icons/question.svg';
    $borderColor = '#aaa';
    if ($counts['kbm'] > $counts['controller']) {
        $majorIcon = 'icons/kbm-drkong.svg';
        $borderColor = '#ba6c06';
    } elseif ($counts['controller'] > $counts['kbm']) {
        $majorIcon = 'icons/controller-lgtong.svg';
        $borderColor = '#fcba03';
    }

    if ($isSteam) {
        $headerURL    = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$gid}/header.jpg";
        $capsuleLgURL = "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$gid}/capsule_231x87.jpg";
    } else {
        $localImage   = findLocalGameImage($gid) ?? 'images/' . $gid . '.jpg';
        $headerURL    = $localImage;
        $capsuleLgURL = $localImage;
    }

    $total = $counts['kbm'] + $counts['controller'];
    $pct   = $total ? ($counts['kbm']/$total)*100 : 50;
    $kbmBar = $pct;
    $ctrlBar = 100 - $pct;

    echo '<div class="game-block collapsed" data-game-id="'.$gid.'" style="border-color:'.$borderColor.'">';
    echo '  <div class="header-row">';
    echo '    <img class="cover" loading="lazy" src="'.htmlspecialchars($capsuleLgURL).'" onerror="this.onerror=null;this.src=\''.htmlspecialchars($headerURL).'\'" alt="&nbsp;">';
    echo '    <h1 class="title"><b>'.htmlspecialchars($game['name']).'</b></h1>';
    echo '    <img class="majority-icon" src="'.$majorIcon.'" alt="majority vote">';
    echo '  </div>';
    echo '  <div class="details">';
    echo '    <div class="game-info">';
    echo '      <p><strong>Developer:</strong> '.htmlspecialchars($game['developer']).'</p>';
    echo '      <p><strong>Publisher:</strong> '.htmlspecialchars($game['publisher']).'</p>';
    echo '      <p><strong>Release Date:</strong> '.htmlspecialchars($game['release_date']).'</p>';
    echo '      <div class="platform-icons">';
    if ($game['supports_windows']) echo '<i class="bi bi-microsoft"></i>';
    if ($game['supports_mac'])     echo '<i class="bi bi-apple"></i>';
    if ($game['supports_linux'])   echo '<i class="bi bi-tux"></i>';
    echo '      </div>';
    echo '    </div>';
    echo '    <div class="vote-row">';
    echo '      <div class="count-label">Votes:<br><span class="count-label-inner">'.$counts['kbm'].' — '.$total.' — '.$counts['controller'].'</span></div>';
    echo '      <div class="vote-section">';
    echo '        <div class="vote-icon kbm-icon '.($your==='kbm'?'active':'').'" data-vote-type="kbm"></div>';
    echo '        <div class="vote-bar">';
    echo '          <div class="bar-fill kbm-bar" style="width:'.$kbmBar.'%"></div>';
    echo '          <div class="bar-fill controller-bar" style="width:'.$ctrlBar.'%"></div>';
    echo '          <div class="tick" style="left:'.$kbmBar.'%"></div>';
    echo '        </div>';
    echo '        <div class="vote-icon controller-icon '.($your==='controller'?'active':'').'" data-vote-type="controller"></div>';
    echo '      </div>';
    echo '    </div>';
    echo '  </div>';
    echo '</div>';
}
