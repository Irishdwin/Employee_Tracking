<?php
/**
 * Unread direct-message counts per other user (sender) for the logged-in viewer.
 *
 * @return array{total:int,by_contact:array<int,int>,threads:array<int,array{thread_id:int,unread_count:int}>}
 */
function messenger_get_unread_counts(mysqli $conn,int $viewerId):array {
 require_once __DIR__.'/messenger_rbac.php';$actor=messenger_user($conn,$viewerId);$out=['total'=>0,'by_contact'=>[],'threads'=>[],'latest_unread'=>[]];if(!$actor)return $out;
 $rows=site_query($conn,'SELECT t.ThreadID,t.ThreadType FROM chat_threads t JOIN chat_thread_participants p ON p.ThreadID=t.ThreadID WHERE p.UserID=?','i',[$viewerId])->fetch_all(MYSQLI_ASSOC);
 foreach($rows as$row){$id=(int)$row['ThreadID'];try{$peers=messenger_require_thread($conn,$actor,$id);}catch(DomainException $e){continue;}
  $ids=array_map('intval',array_column($peers,'user_id'));$idsSql=implode(',',$ids);
  $counts=site_query($conn,"SELECT m.SenderUserID,COUNT(*) n,MAX(m.CreatedAt) latest_at,MAX(m.MessageID) latest_id FROM chat_messages m WHERE m.ThreadID=? AND m.IsSystemMessage=0 AND m.SenderUserID IN ($idsSql) AND m.MessageID>COALESCE((SELECT LastReadMessageID FROM chat_thread_reads WHERE ThreadID=? AND UserID=?),0) GROUP BY m.SenderUserID",'iii',[$id,$id,$viewerId])->fetch_all(MYSQLI_ASSOC);
  foreach($counts as$c){$other=(int)$c['SenderUserID'];$n=(int)$c['n'];$out['by_contact'][$other]=($out['by_contact'][$other]??0)+$n;$out['total']+=$n;$latest=['created_at'=>$c['latest_at'],'message_id'=>(int)$c['latest_id']];$previous=$out['latest_unread'][$other]??null;if(!$previous||strcmp((string)$latest['created_at'],(string)$previous['created_at'])>0||($latest['created_at']===$previous['created_at']&&$latest['message_id']>$previous['message_id']))$out['latest_unread'][$other]=$latest;}
  foreach($ids as$other)if($row['ThreadType']==='DIRECT')$out['threads'][$other]=['thread_id'=>$id,'unread_count'=>$out['by_contact'][$other]??0];
 }return $out;
}

/**
 * Mark a DIRECT thread with another user as read for the viewer.
 */
function messenger_mark_read_with_user(mysqli $conn, int $viewerId, int $otherUserId): ?int
{
    $pairKey = 'dm_' . min($viewerId, $otherUserId) . '_' . max($viewerId, $otherUserId);
    $stmt = $conn->prepare("
        SELECT t.ThreadID
        FROM chat_threads t
        INNER JOIN chat_thread_participants p ON p.ThreadID = t.ThreadID AND p.UserID = ?
        WHERE t.ThreadType = 'DIRECT' AND t.RelatedModule = ?
        LIMIT 1
    ");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('is', $viewerId, $pairKey);
    if (!$stmt->execute()) {
        $stmt->close();
        return null;
    }
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row || empty($row['ThreadID'])) {
        return null;
    }
    return (int)$row['ThreadID'];
}
