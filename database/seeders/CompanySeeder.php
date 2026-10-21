<?php
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    $seed->put('company_sections','company.history',[
        'title'=>'Our story in Myanmar','title_my'=>'မြန်မာနိုင်ငံရှိ ကျွန်ုပ်တို့၏ သမိုင်း',
        'description'=>'<p>Royal Myanmar King Company Limited has imported SUPERKING LUBRICANTS from the UAE and distributed the brand in Myanmar since 2016.</p><p>The company is preparing to establish local manufacturing operations in Myanmar.</p>',
        'description_my'=>'<p>ရွိုင်ရယ် မြန်မာကင်း ကုမ္ပဏီလီမိတက်သည် SUPERKING ချောဆီများကို ၂၀၁၆ ခုနှစ်မှစ၍ ယူအေအီးမှ တင်သွင်းပြီး မြန်မာနိုင်ငံတွင် ဖြန့်ချိခဲ့ပါသည်။</p><p>ယခု ပြည်တွင်းထုတ်လုပ်မှုအတွက် ပြင်ဆင်နေပါသည်။</p>',
        'image'=>null,'sort_order'=>0,'published'=>1
    ]);
    foreach(['customer-support'=>'Customer support','distribution'=>'Distribution partnerships'] as $key=>$title)$seed->put('company_sections','demo.company.'.$key,[
        'title'=>'Demo: '.$title,'title_my'=>'နမူနာ: '.$title,'description'=>'<p>Demo staff-editing example. Add approved '.$title.' information here before publishing.</p>',
        'description_my'=>'<p>ဝန်ထမ်းများ စမ်းသပ်ပြင်ဆင်ရန် နမူနာဖြစ်ပါသည်။ အတည်ပြုထားသော အချက်အလက်ဖြင့် အစားထိုးပါ။</p>','image'=>null,'sort_order'=>$key==='customer-support'?1:2,'published'=>0
    ]);
    foreach([
        ['products','Explore the product range','ကုန်ပစ္စည်းများကို လေ့လာရန်','Product information and technical documentation.','ကုန်ပစ္စည်းနှင့် နည်းပညာအချက်အလက်များ။','Explore products','ကုန်ပစ္စည်းများကြည့်ရန်','/products'],
        ['distributor','Grow with SUPERKING','SUPERKING နှင့်အတူ တိုးတက်ရန်','Distributor and export inquiry example.','ဖြန့်ချိရေးနှင့် တင်ပို့ရေး မေးမြန်းမှု နမူနာ။','Become a distributor','ဖြန့်ချိသူ ဖြစ်လိုပါသလား','/distributor']
    ] as $order=>[$key,$title,$my,$description,$descriptionMy,$button,$buttonMy,$url])$seed->put('slides','demo.banner.'.$key,[
        'title'=>'Demo: '.$title,'title_my'=>'နမူနာ: '.$my,'description'=>'Demo banner — '.$description,'description_my'=>'နမူနာ — '.$descriptionMy,'button_text'=>$button,'button_text_my'=>$buttonMy,'button_url'=>$url,'image'=>null,'sort_order'=>$order,'published'=>0
    ]);
    foreach(['support'=>'Customer support','sales'=>'Sales & distribution','export'=>'International business'] as $key=>$title)$seed->put('contacts','demo.contact.'.$key,[
        'title'=>'Demo: '.$title,'title_my'=>'နမူနာ: '.$title,'email'=>$key.'@example.invalid','phone'=>'',
        'address'=>'DEMO ONLY — replace with a verified company address.','address_my'=>'နမူနာသာ ဖြစ်ပါသည်။ အတည်ပြုထားသော ကုမ္ပဏီလိပ်စာဖြင့် အစားထိုးပါ။',
        'hours'=>'Demo: add confirmed office hours.','hours_my'=>'နမူနာ: အတည်ပြု ရုံးဖွင့်ချိန်ကို ထည့်ပါ။','sort_order'=>['support'=>0,'sales'=>1,'export'=>2][$key],'published'=>0
    ]);
};
