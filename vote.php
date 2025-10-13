<?php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$input    = json_decode(file_get_contents('php://input'), true);

// CSRF
if (($input['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['success'=>false,'alertKey'=>'voteInvalidCsrf']);
    exit;
}

// 1) Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode([
      'success' => false,
      'alertKey'   => 'voteNotLoggedIn'
    ]);
    exit;
}

// Rate limit
$now  = time();
$last = $_SESSION['last_vote_time'] ?? 0;
if ($now - $last < 2) {
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'alertKey'   => 'voteRateLimit'
    ]);
    exit;
}
$_SESSION['last_vote_time'] = $now;

$userId = (int)$_SESSION['user_id'];

// 2) Read & validate input
$gameId   = intval($input['game_id'] ?? 0);
$voteType = $input['vote_type'] ?? '';

if (!$gameId || ! in_array($voteType, ['kbm','controller'], true)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'alertKey'=>'voteInvalidInput']);
    exit;
}

try {
    // 3) Look for existing vote
    $stmt = $pdo->prepare(
        'SELECT vote_type 
           FROM votes 
          WHERE game_id = :g 
            AND user_id = :u'
    );
    $stmt->execute([':g'=>$gameId,':u'=>$userId]);
    $existing = $stmt->fetchColumn();

    if ($existing === false) {
        // Insert
        $stmt = $pdo->prepare(
            'INSERT INTO votes (game_id,user_id,vote_type) VALUES (:g,:u,:t)'
        );
        $stmt->execute([':g'=>$gameId,':u'=>$userId,':t'=>$voteType]);
    } elseif ($existing === $voteType) {
        // Unvote
        $stmt = $pdo->prepare(
            'DELETE FROM votes WHERE game_id = :g AND user_id = :u'
        );
        $stmt->execute([':g'=>$gameId,':u'=>$userId]);
    } else {
        // Change vote
        $stmt = $pdo->prepare(
            'UPDATE votes SET vote_type = :t WHERE game_id = :g AND user_id = :u'
        );
        $stmt->execute([':t'=>$voteType,':g'=>$gameId,':u'=>$userId]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'alertKey'=>'voteServerError']);
    exit;
}

// 4) Fetch updated tallies
$stmt = $pdo->prepare(
    'SELECT vote_type, COUNT(*) AS cnt 
       FROM votes 
      WHERE game_id = :g 
   GROUP BY vote_type'
);
$stmt->execute([':g'=>$gameId]);
$results = $stmt->fetchAll();

// 5) Build response
$counts = ['kbm'=>0,'controller'=>0];
foreach ($results as $row) {
    $counts[$row['vote_type']] = (int)$row['cnt'];
}

echo json_encode([
    'success'    => true,
    'kbm'        => $counts['kbm'],
    'controller' => $counts['controller']
]);
exit;
