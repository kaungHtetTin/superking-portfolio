<?php
// Stable identities keep reruns from duplicating records staff have renamed.
$statements=[];
foreach(['categories','products','slides','company_sections','contacts','articles','manufacturing_updates','dealers','documents','inquiries','admin_activity'] as $seedTable) {
    $statements[]="ALTER TABLE $seedTable ADD seed_key VARCHAR(100) NULL UNIQUE";
}
return ['up'=>$statements,'down'=>[]];
