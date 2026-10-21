<?php
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    foreach([
        ['yangon','Yangon','Yangon'],['mandalay','Mandalay','Mandalay'],['naypyitaw','Nay Pyi Taw','Nay Pyi Taw'],
        ['bago','Bago','Bago'],['taunggyi','Shan','Taunggyi'],['mawlamyine','Mon','Mawlamyine']
    ] as $order=>[$key,$region,$city])$seed->put('dealers','demo.dealer.'.$key,[
        'title'=>'Demo '.$city.' Dealer','title_my'=>'နမူနာ '.$city.' အရောင်းကိုယ်စားလှယ်',
        'description'=>'Fictional directory entry for filtering and CMS training. This is not an authorized or operating dealer.',
        'description_my'=>'စာရင်းရှာဖွေမှုနှင့် CMS စမ်းသပ်ရန် ဖန်တီးထားသော နမူနာသာ ဖြစ်ပါသည်။ တကယ့် တရားဝင်အရောင်းကိုယ်စားလှယ် မဟုတ်ပါ။',
        'region'=>$region,'city'=>$city,'township'=>'Sample township','address'=>'DEMO ONLY — no dealer is confirmed at this sample location.','phone'=>'','map_url'=>'','verified'=>0,'sort_order'=>$order,'published'=>0
    ]);
};
