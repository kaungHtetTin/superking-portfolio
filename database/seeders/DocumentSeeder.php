<?php
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    foreach([
        ['company_profile','Company profile','ကုမ္ပဏီမိတ်ဆက်','demo-company-profile.pdf'],
        ['catalogue','Product catalogue','ကုန်ပစ္စည်းစာရင်း','demo-product-catalogue.pdf'],
        ['technical','Technical document','နည်းပညာစာရွက်စာတမ်း','demo-technical-document.pdf'],
        ['certificate','Certificate placeholder','လက်မှတ် နမူနာနေရာ','demo-certificate-placeholder.pdf']
    ] as $order=>[$type,$title,$my,$file]){
        $path='assets/documents/demo/'.$file;
        if(!is_file(BASE_PATH.'/public/'.$path))throw new \RuntimeException('Missing demo attachment: '.$path);
        $seed->put('documents','demo.document.'.$type,[
            'title'=>'Demo: '.$title.' (not approved)','title_my'=>'နမူနာ: '.$my.' (အတည်မပြုရသေးပါ)',
            'description'=>'Training attachment only. This is not an approved product document, technical data sheet or certification. Replace with a genuine, approved PDF.',
            'description_my'=>'စမ်းသပ်လေ့ကျင့်ရန်သာ ဖြစ်ပါသည်။ အတည်ပြုထားသော နည်းပညာစာရွက်စာတမ်း သို့မဟုတ် လက်မှတ် မဟုတ်ပါ။ တရားဝင် အတည်ပြု PDF ဖြင့် အစားထိုးပါ။',
            'document_type'=>$type,'file'=>$path,'verified'=>0,'sort_order'=>$order,'published'=>0
        ]);
    }
};
