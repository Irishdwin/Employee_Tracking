<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../../Api/Db_connection/db_connect.php';
require __DIR__.'/../../Api/Helpers/list_query_page.php';
$conn->query('CREATE TEMPORARY TABLE list_fixture (ID INT,Name VARCHAR(100),Company VARCHAR(100),Lead VARCHAR(100))');
$stmt=$conn->prepare('INSERT INTO list_fixture VALUES (?,?,?,?)');
for($i=1;$i<=37;$i++){ $name='Applicant '.$i; $company=$i%2?'Uptown':'Downtown';$lead='Multi-site lead';$stmt->bind_param('isss',$i,$name,$company,$lead);$stmt->execute(); }
function runList($params){global $conn;$_GET=$params;[$sql,$types,$values,$meta]=list_query_page($conn,'SELECT ID,Name,Company,Lead FROM list_fixture ORDER BY ID DESC',['ID','Name'],['company'=>'Company','lead'=>'Lead']);$s=$conn->prepare($sql);$s->bind_param($types,...$values);$s->execute();return [$s->get_result()->fetch_all(MYSQLI_ASSOC),$meta];}
function ok($value,$label){if(!$value)throw new Exception($label);echo "PASS $label\n";}
[$rows,$meta]=runList(['page'=>2,'include_filters'=>'1']);ok(count($rows)===10 && $meta['total']===37 && $meta['pages']===4 && $rows[0]['ID']===27,'bounded page 2 and full count');ok(count($meta['filters']['company'])===2 && count($meta['filters']['lead'])===1,'one lead supports both companies');
[$rows,$meta]=runList(['page'=>99,'search'=>'Applicant 1','company'=>'Uptown']);ok($meta['page']===1 && $meta['total']===6,'search and company combine; page clamped');
[$rows,$meta]=runList(['page'=>1,'search'=>"' OR 1=1 --"]);ok(!$rows && $meta['total']===0,'SQL injection remains literal');
[$rows,$meta]=runList(['page'=>1,'search'=>'%']);ok(!$rows,'LIKE wildcard search is literal');
[$rows,$meta]=runList(['page'=>1]);ok($meta['total']===37,'reset restores full dataset');
[$rows,$meta]=runList(['page'=>1,'page_size'=>25,'lead'=>'Multi-site lead']);ok(count($rows)===25 && $meta['total']===37,'page size and multi-site lead');
