<?php
// Public active lookup for the public job wall; writes remain Admin-only in manage_* APIs.
require_once __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/job_hierarchy.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);echo json_encode(['success'=>false,'message'=>'GET required.']);exit;}
try{echo json_encode(['success'=>true,'categories'=>jh_tree($conn)],JSON_UNESCAPED_UNICODE);}catch(Throwable $e){error_log('job_hierarchy: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Could not load job hierarchy.']);}
