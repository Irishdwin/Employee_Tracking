<?php
 declare(strict_types=1);
 require_once __DIR__.'/../Common/session_init.php';
 require __DIR__.'/../Db_connection/db_connect.php';
 require_once __DIR__.'/../Helpers/payroll_status_service.php';
 header('Content-Type: application/json; charset=utf-8');
 $proof=null;
 try {
     if ($_SERVER['REQUEST_METHOD']!=='POST') {http_response_code(405);exit;}
     $actor=payroll_require_accounting($conn,true);
     payroll_status_ensure_schema($conn);
     $start=trim((string)($_POST['cutoff_start'] ?? '')); $end=trim((string)($_POST['cutoff_end'] ?? ''));
     foreach([$start,$end] as $date) {
         $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
         if (!$parsed || $parsed->format('Y-m-d')!==$date) throw new InvalidArgumentException('Choose valid cutoff dates.');
     }
     if ($end<$start) throw new InvalidArgumentException('Invalid cutoff range.');
     $proof=payroll_save_proof($_FILES['proof_image'] ?? null);
     $result=payroll_status_update_group($conn,(int)($_POST['site_supervisor_id'] ?? 0),(int)($_POST['company_id'] ?? 0),$start,$end,trim((string)($_POST['status'] ?? '')),trim((string)($_POST['remarks'] ?? '')),$proof,$actor);
     if (empty($result['changed']) && $proof) @unlink(dirname(__DIR__,2).'/'.$proof);
     echo json_encode($result);
 } catch(Throwable $e) {
     if ($proof) @unlink(dirname(__DIR__,2).'/'.$proof);
     payroll_api_error($e);
 }
