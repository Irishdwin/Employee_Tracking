<?php
require_once __DIR__.'/../Common/session_init.php';
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/messenger_rbac.php';
require_once __DIR__.'/../Helpers/messenger_unread.php';
header('Content-Type: application/json');
try{$actor=messenger_actor($conn);if($actor['role']!=='Applicant')throw new DomainException('Applicant login required.',403);echo json_encode(['success'=>true,'total_unread'=>messenger_get_unread_counts($conn,$actor['user_id'])['total'],'preview'=>null]);}catch(Throwable $e){messenger_api_error($e);}
