<?php
// inc/_global/quests/be_followed_10.php
//
// Quest: “Become Popular: 10 Followers”
//
//—Checks how many users are currently following the current user. On each follow/unfollow,
// updates a row in demon_user_quests with progress_count. Once that live count hits 10,
// marks is_completed = 1, inserts credit logs, and updates balance.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int)($_SESSION['user_id'] ?? 0);
if ($userId === 0) {
    return; // not logged in
}

// 1) Fetch quest definition for 'be_followed_10'
$questKey = 'be_followed_10';
$qStmt = $pdo->prepare("
    SELECT
      id,
      reward_amount,
      is_repeatable,
      is_active,
      threshold_count
    FROM demon_quests
    WHERE quest_key = :qk
      AND is_active = 1
    LIMIT 1
");
$qStmt->execute(['qk' => $questKey]);
$quest = $qStmt->fetch(PDO::FETCH_ASSOC);

if (!$quest) {
    return; // quest not found or inactive
}

$questId       = (int)$quest['id'];
$rewardAmount  = (int)$quest['reward_amount'];
$threshold     = max(1, (int)$quest['threshold_count']); // should be 10 per SQL insert

// 2) Count how many users are following this user (live)
$countStmt = $pdo->prepare("
    SELECT COUNT(*) AS cnt
    FROM demon_follows
    WHERE user_id = :uid
");
$countStmt->execute(['uid' => $userId]);
$followerCount = (int)$countStmt->fetchColumn();

// 3) Fetch the existing demon_user_quests row for this quest (if any)
$userQuestStmt = $pdo->prepare("
    SELECT id, progress_count, is_completed
    FROM demon_user_quests
    WHERE user_id  = :uid
      AND quest_id = :qid
    LIMIT 1
");
$userQuestStmt->execute([
    'uid' => $userId,
    'qid' => $questId
]);
$userQuestRow = $userQuestStmt->fetch(PDO::FETCH_ASSOC);

try {
    $pdo->beginTransaction();

    if (!$userQuestRow) {
        // a) No row yet → insert a “progress” row (is_completed = 0 for now)
        $insertQuest = $pdo->prepare("
            INSERT INTO demon_user_quests
              (user_id, quest_key, quest_id, progress_count, is_completed, last_updated)
            VALUES
              (:uid, :qk, :qid, :pc, 0, NOW())
        ");
        $insertQuest->execute([
            'uid' => $userId,
            'qk'  => $questKey,
            'qid' => $questId,
            'pc'  => $followerCount
        ]);
        $uqId        = (int)$pdo->lastInsertId();
        $alreadyDone = false;
        $oldProgress = 0;
    } else {
        // b) Row exists → update progress_count if not yet completed
        $uqId        = (int)$userQuestRow['id'];
        $oldProgress = (int)$userQuestRow['progress_count'];
        $alreadyDone = ((int)$userQuestRow['is_completed'] === 1);

        if (! $alreadyDone) {
            $updateQuest = $pdo->prepare("
                UPDATE demon_user_quests
                SET progress_count = :pc,
                    last_updated   = NOW()
                WHERE id = :id
            ");
            $updateQuest->execute([
                'pc' => $followerCount,
                'id' => $uqId
            ]);
        }
    }

    // 4) If not yet completed and the live count ≥ threshold → mark completed & award
    if (! $alreadyDone && $followerCount >= $threshold) {
        // a) Mark this quest row as completed
        $completeStmt = $pdo->prepare("
            UPDATE demon_user_quests
            SET is_completed   = 1,
                completed_at   = NOW(),
                progress_count = :pc,  /* ensure we store threshold here */
                last_updated   = NOW()
            WHERE id = :id
        ");
        $completeStmt->execute([
            'pc' => $threshold,
            'id' => $uqId
        ]);

        // b) Award credits if rewardAmount > 0
        if ($rewardAmount > 0) {
            // b.i) Insert into demon_credit_logs
            $logStmt = $pdo->prepare("
                INSERT INTO demon_credit_logs
                  (user_id, change_amount, type, reason, description, created_at, quest_key)
                VALUES
                  (:uid, :amt, 'earn', :reason, 'Got {$threshold} followers', NOW(), :qk)
            ");
            $logStmt->execute([
                'uid'    => $userId,
                'amt'    => $rewardAmount,
                'reason' => 'Quest: be_followed_10',
                'qk'     => $questKey
            ]);

            // b.ii) Update demon_users.credit_balance
            $updBal = $pdo->prepare("
                UPDATE demon_users
                SET credit_balance = credit_balance + :amt
                WHERE id = :uid
            ");
            $updBal->execute([
                'amt' => $rewardAmount,
                'uid' => $userId
            ]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Silently fail on error
    return;
}
