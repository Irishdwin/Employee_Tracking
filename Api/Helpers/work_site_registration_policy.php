<?php
/** Registration quality is independent of the employee attendance radius rule. */
function work_site_registration_policy(): array {
    $configured=getenv('WORK_SITE_REGISTRATION_MAX_ACCURACY_METERS');
    $limit=$configured===false?100.0:filter_var($configured,FILTER_VALIDATE_FLOAT);
    if($limit===false||!is_finite((float)$limit)||$limit<=0||$limit>1000)$limit=100.0;
    return ['max_accuracy_meters'=>(float)$limit,'acquisition_seconds'=>30];
}
function work_site_registration_accuracy_allowed($accuracy): bool {
    return is_numeric($accuracy)&&is_finite((float)$accuracy)&&(float)$accuracy>=0
        &&(float)$accuracy<=work_site_registration_policy()['max_accuracy_meters'];
}
