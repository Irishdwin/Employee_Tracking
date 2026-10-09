<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require __DIR__.'/../Db_connection/db_connect.php';
require_once __DIR__.'/../Helpers/overtime_workflow.php';
$lock=otw_query($conn,"SELECT GET_LOCK('onazul_ot_auto_timeout',0) locked")[0];
if(!(int)$lock['locked'])exit;
try { echo 'Auto-closed overtime sessions: '.otw_auto_close($conn,date('Y-m-d H:i:s')).PHP_EOL; }
finally { otw_query($conn,"SELECT RELEASE_LOCK('onazul_ot_auto_timeout')"); }
