<?php
/** Read-only access to the existing Admin-managed hierarchy. No schema/seeding side effects. */
function jh_rows(mysqli $db,string $sql,string $types='',array $params=[]):array{
    $s=$db->prepare($sql);if($types!=='')$s->bind_param($types,...$params);$s->execute();$r=$s->get_result()->fetch_all(MYSQLI_ASSOC);$s->close();return $r;
}
function jh_id($value):int{
    if($value===null||$value==='')return 0;
    $id=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($id===false)throw new DomainException('Invalid hierarchy ID.',400);return $id;
}
function jh_validate(mysqli $db,int $category,int $classification,int $sub,bool $required=true):array{
    if(($required&&(!$category||!$classification||!$sub))||($classification&&!$category)||($sub&&!$classification))throw new DomainException('Select a category, its classification, and its sub-classification in order.',400);
    if(!$category)return [];
    $sql='SELECT c.id category_id,c.category_name,k.id classification_id,k.classification_name,s.id sub_classification_id,s.sub_classification_name FROM job_categories c LEFT JOIN job_classifications k ON k.category_id=c.id AND k.is_active=1 LEFT JOIN job_sub_classifications s ON s.classification_id=k.id AND s.is_active=1 WHERE c.is_active=1 AND c.id=?';$types='i';$params=[$category];
    if($classification){$sql.=' AND k.id=?';$types.='i';$params[]=$classification;}
    if($sub){$sql.=' AND s.id=?';$types.='i';$params[]=$sub;}
    $rows=jh_rows($db,$sql.' LIMIT 1',$types,$params);
    if(!$rows)throw new DomainException('The selected hierarchy is inactive or does not belong together.',400);return $rows[0];
}
function jh_resolve_profile(mysqli $db,array $input):array{
    $c=jh_id($input['category_id']??null);$k=jh_id($input['classification_id']??null);$s=jh_id($input['sub_classification_id']??null);
    if($c||$k||$s)return jh_validate($db,$c,$k,$s);
    $rows=jh_rows($db,'SELECT c.id category_id,k.id classification_id,s.id sub_classification_id FROM job_categories c JOIN job_classifications k ON k.category_id=c.id JOIN job_sub_classifications s ON s.classification_id=k.id WHERE c.category_name=? AND k.classification_name=? AND s.sub_classification_name=?','sss',[(string)($input['job_category']??''),(string)($input['preferred_classification']??''),(string)($input['preferred_sub_classification']??'')]);
    if(count($rows)!==1)throw new DomainException('Select valid preferences from the Admin-managed job hierarchy.',400);
    return jh_validate($db,(int)$rows[0]['category_id'],(int)$rows[0]['classification_id'],(int)$rows[0]['sub_classification_id']);
}
function jh_tree(mysqli $db):array{
    $cats=jh_rows($db,'SELECT id,category_name FROM job_categories WHERE is_active=1 ORDER BY category_name');
    $classes=jh_rows($db,'SELECT id,category_id,classification_name,is_default FROM job_classifications WHERE is_active=1 ORDER BY classification_name');
    $subs=jh_rows($db,'SELECT id,classification_id,sub_classification_name FROM job_sub_classifications WHERE is_active=1 ORDER BY sub_classification_name');
    foreach($cats as &$c){$c['classifications']=[];foreach($classes as $k)if($k['category_id']===$c['id']){$k['sub_classifications']=array_values(array_filter($subs,fn($s)=>$s['classification_id']===$k['id']));$c['classifications'][]=$k;}}unset($c);return $cats;
}
/** Same tuple must match one available placement; never combine fields from different placements. */
function jh_filter(mysqli $db,int $c,int $k,int $s):array{
    jh_validate($db,$c,$k,$s,false);$types='';$params=[];$header=[];$detail=[];
    foreach(['category_id'=>$c,'classification_id'=>$k,'sub_classification_id'=>$s] as $col=>$id)if($id){$header[]="jo.$col=?";$detail[]="hd.$col=?";$types.='i';$params[]=$id;}
    if(!$params)return ['where'=>'','types'=>'','params'=>[]];
    $available="(SELECT COUNT(*) FROM job_applications ha WHERE ha.JobOrderID=jo.JobOrderID AND ha.target_detail_id=hd.detail_id AND ha.HiredDate IS NOT NULL)<GREATEST(hd.required_workers,1)";
    return ['where'=>' AND ((NOT EXISTS (SELECT 1 FROM job_order_details h0 WHERE h0.JobOrderID=jo.JobOrderID) AND '.implode(' AND ',$header).') OR EXISTS (SELECT 1 FROM job_order_details hd WHERE hd.JobOrderID=jo.JobOrderID AND '.implode(' AND ',$detail).' AND '.$available.'))','types'=>$types.$types,'params'=>array_merge($params,$params)];
}
function jh_profile_filter(mysqli $db,int $uid):array{
    $rows=jh_rows($db,'SELECT category_id,classification_id,sub_classification_id,JobCategory job_category,PreferredClassification preferred_classification,PreferredSubClassification preferred_sub_classification FROM applicants WHERE UserID=?','i',[$uid]);
    try{if(!$rows)throw new DomainException('Profile missing');$h=jh_resolve_profile($db,$rows[0]);foreach(['category_id','classification_id','sub_classification_id'] as $key){$wanted=jh_id($_GET[$key]??null);if($wanted&&$wanted!==(int)$h[$key])return ['where'=>' AND 1=0','types'=>'','params'=>[]];}return jh_filter($db,(int)$h['category_id'],(int)$h['classification_id'],(int)$h['sub_classification_id']);}
    catch(DomainException $e){return ['where'=>' AND 1=0','types'=>'','params'=>[]];}
}
function jh_profile_labels(mysqli $db,int $uid):array{
    return jh_rows($db,'SELECT COALESCE(c.category_name,a.JobCategory) category_name,COALESCE(k.classification_name,a.PreferredClassification) classification_name,COALESCE(s.sub_classification_name,a.PreferredSubClassification) sub_classification_name FROM applicants a LEFT JOIN job_categories c ON c.id=a.category_id LEFT JOIN job_classifications k ON k.id=a.classification_id LEFT JOIN job_sub_classifications s ON s.id=a.sub_classification_id WHERE a.UserID=?','i',[$uid])[0]??[];
}
function jh_request_filter(mysqli $db,array $query):array{
    try{return jh_filter($db,jh_id($query['category_id']??null),jh_id($query['classification_id']??null),jh_id($query['sub_classification_id']??null));}
    catch(DomainException $e){http_response_code(400);echo json_encode(['success'=>false,'message'=>$e->getMessage(),'jobs'=>[]]);exit;}
}
function jh_filter_slots(mysqli $db,array $slots,int $orderId,int $profileUid=0):array{
    $constraints=[];$wanted=[];
    foreach(['category_id','classification_id','sub_classification_id'] as $key)if(!empty($_GET[$key]))$wanted[$key]=(int)$_GET[$key];
    if($wanted)$constraints[]=$wanted;
    if($profileUid){$r=jh_rows($db,'SELECT category_id,classification_id,sub_classification_id,JobCategory job_category,PreferredClassification preferred_classification,PreferredSubClassification preferred_sub_classification FROM applicants WHERE UserID=?','i',[$profileUid]);try{$h=jh_resolve_profile($db,$r[0]??[]);$constraints[]=array_intersect_key($h,array_flip(['category_id','classification_id','sub_classification_id']));}catch(DomainException $e){return [];}}
    if(!$constraints)return $slots;
    $header=jh_rows($db,'SELECT category_id,classification_id,sub_classification_id FROM joborder WHERE JobOrderID=?','i',[$orderId])[0]??[];
    return array_values(array_filter($slots,function($slot)use($constraints,$header){$h=!empty($slot['detail_id'])?$slot:$header;foreach($constraints as $wanted)foreach($wanted as $key=>$id)if((int)($h[$key]??0)!==(int)$id)return false;return true;}));
}
