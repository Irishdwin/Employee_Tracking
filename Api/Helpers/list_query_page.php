<?php
/** Optional bounded list queries, used only when page is requested. Authorization remains in each endpoint. */
function list_query_page($conn, $sql, $searchColumns, $filterColumns, $types = '', $values = []) {
    $meta = null;
    if (!isset($_GET['page'])) return [$sql, $types, $values, $meta];
    foreach (['page','page_size','search',...array_keys($filterColumns)] as $key) if(isset($_GET[$key]) && !is_scalar($_GET[$key])) list_query_invalid();
    $page = filter_var($_GET['page'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>1000000]]);
    $size = filter_var($_GET['page_size'] ?? 10, FILTER_VALIDATE_INT);
    if (!$page || !in_array($size, [10,25,50], true)) list_query_invalid();
    $search = trim((string)($_GET['search'] ?? ''));
    if (strlen($search)>200) list_query_invalid();
    $base = preg_replace('/\s+ORDER BY\s+[^;]+$/i', '', trim($sql));
    $where = []; $extraTypes = ''; $extraValues = [];
    if ($search !== '') {
        $parts=[];
        foreach ($searchColumns as $column) { $parts[]="$column LIKE ?"; $extraTypes.='s'; $extraValues[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$search).'%'; }
        $where[]='('.implode(' OR ',array_map(fn($p)=>$p." ESCAPE '!'",$parts)).')';
    }
    $options=[];
    foreach ($filterColumns as $key=>$column) {
        if (($_GET['include_filters']??'')==='1') {
        $stmt=$conn->prepare("SELECT DISTINCT $column AS value FROM ($base) list_rows WHERE $column IS NOT NULL AND $column<>'' ORDER BY value");
        if ($types!=='') $stmt->bind_param($types,...$values);
        $stmt->execute(); $options[$key]=array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC),'value'); $stmt->close();
        }
        $value=trim((string)($_GET[$key]??''));
        if ($value==='') continue;
        if (strlen($value)>255) list_query_invalid();
        $where[]="$column = ?"; $extraTypes.='s'; $extraValues[]=$value;
    }
    $condition=$where?' WHERE '.implode(' AND ',$where):'';
    $allTypes=$types.$extraTypes; $allValues=array_merge($values,$extraValues);
    $stmt=$conn->prepare("SELECT COUNT(*) total FROM ($base) list_rows$condition");
    if ($allTypes!=='') $stmt->bind_param($allTypes,...$allValues);
    $stmt->execute(); $total=(int)$stmt->get_result()->fetch_assoc()['total']; $stmt->close();
    $pages=max(1,(int)ceil($total/$size)); $page=min($page,$pages); $offset=($page-1)*$size;
    // Preserve the existing ordering inside the derived query and add a stable identity tie-breaker outside.
    $identity=$searchColumns[0];
    $order='';
    if (preg_match('/ORDER BY\s+\w+\.([A-Za-z0-9_]+)\s+(ASC|DESC)/i',$sql,$m)) $order=$m[1].' '.$m[2].', ';
    $paged="SELECT * FROM ($base) list_rows$condition ORDER BY $order$identity DESC LIMIT ? OFFSET ?";
    $meta=['page'=>$page,'page_size'=>$size,'total'=>$total,'pages'=>$pages,'filters'=>$options];
    return [$paged,$allTypes.'ii',array_merge($allValues,[$size,$offset]),$meta];
}
function list_query_invalid() { http_response_code(422); echo json_encode(['success'=>false,'message'=>'Invalid list parameters.']); exit; }
