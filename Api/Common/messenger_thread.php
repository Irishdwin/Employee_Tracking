<?php
require_once __DIR__.'/session_init.php';header('Content-Type: application/json');require __DIR__.'/../Db_connection/db_connect.php';require_once __DIR__.'/../Helpers/chat_helper.php';require_once __DIR__.'/../Helpers/messenger_rbac.php';
try{$actor=messenger_actor($conn);$input=json_decode(file_get_contents('php://input'),true)??[];$other=messenger_require_recipient($conn,$actor,(int)($input['user_id']??$_GET['user_id']??0));$a=$actor['user_id'];$b=$other['user_id'];$key='dm_'.min($a,$b).'_'.max($a,$b);
 $lock=site_query($conn,'SELECT GET_LOCK(?,5) locked','s',['messenger_'.$key])->fetch_assoc();if(!(int)$lock['locked'])throw new RuntimeException();
 try{$row=site_query($conn,"SELECT ThreadID FROM chat_threads WHERE ThreadType='DIRECT' AND RelatedModule=? ORDER BY ThreadID LIMIT 1",'s',[$key])->fetch_assoc();$id=$row?(int)$row['ThreadID']:chat_find_or_create_thread($conn,'DIRECT',$key,max($a,$b),$a,[$a,$b]);messenger_require_thread($conn,$actor,$id);}finally{site_query($conn,'SELECT RELEASE_LOCK(?)','s',['messenger_'.$key]);}
 echo json_encode(['success'=>true,'thread_id'=>$id,'user_id'=>$b]);}catch(Throwable $e){messenger_api_error($e);}
