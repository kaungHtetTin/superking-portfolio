<?php
use Mini\Database as DB;
use Superking\Seeders\SeedContext;

$seedProfile=$seedProfile??(string)env('SEED_PROFILE','demo');
if(!in_array($seedProfile,['demo','minimal'],true))throw new InvalidArgumentException('SEED_PROFILE must be demo or minimal.');
require_once BASE_PATH.'/database/seeders/SeedContext.php';
$context=new SeedContext();
$seeders=['BootstrapSeeder'];
if($seedProfile==='demo')$seeders=array_merge($seeders,['ProductSeeder','CompanySeeder','ArticleSeeder','ManufacturingSeeder','DealerSeeder','DocumentSeeder','InquirySeeder','ActivitySeeder']);
DB::transaction(static function()use($seeders,$context):void {
    foreach($seeders as $seeder){
        $run=(static fn(string $path)=>require $path)(BASE_PATH.'/database/seeders/'.$seeder.'.php');
        $run($context);
    }
});
echo 'Seed profile: '.$seedProfile."\n";
foreach($context->counts as $table=>$counts)echo str_pad($table,24).$counts['created'].' created; '.$counts['preserved']." preserved\n";
if($context->initialPassword!==null)echo "Admin email: admin@superking.local\nInitial password: ".$context->initialPassword."\nChange these in Admin > Your account.\n";
echo "Seed complete. Existing records, publication choices and administrator credentials were preserved.\n";
if($seedProfile==='demo')echo "Demo content is draft/unverified; only company history supplied in the brief is published. Login/spam counters remain system-managed.\n";
