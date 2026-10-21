<?php
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    $topics=[
        ['fundamentals','Engine Oil Fundamentals','Engine oil fundamentals','အင်ဂျင်ဝိုင် အခြေခံဗဟုသုတ','motorcycle'],
        ['motorcycle','Motorcycle Lubricants','Choosing motorcycle lubricants','မော်တော်ဆိုင်ကယ် ချောဆီ ရွေးချယ်ခြင်း','motorcycle'],
        ['passenger','Passenger Car Lubricants','Reading your vehicle oil requirements','ယာဉ်၏ ချောဆီလိုအပ်ချက်များကို ဖတ်ရှုခြင်း','passenger'],
        ['heavy-duty','Heavy-Duty & Commercial Vehicles','Commercial vehicle lubricant selection','လုပ်ငန်းသုံးယာဉ် ချောဆီရွေးချယ်ခြင်း','heavy-duty'],
        ['other','Other Lubricants','Hydraulic, gear and transmission products','ဟိုက်ဒရောလစ်၊ ဂီယာနှင့် ထရန်စမစ်ရှင်ဆီများ','industrial'],
        ['conditions','Myanmar Operating Conditions','Questions about local operating conditions','ပြည်တွင်းအသုံးပြုမှုဆိုင်ရာ မေးခွန်းများ','heavy-duty'],
        ['faq','Frequently Asked Questions','Product questions and genuine-product support','ကုန်ပစ္စည်းမေးခွန်းနှင့် စစ်မှန်မှုဆိုင်ရာ အကူအညီ','transmission']
    ];
    foreach($topics as $order=>[$key,$topic,$title,$my,$product])$seed->put('articles','demo.knowledge.'.$key,[
        'title'=>'Demo guide: '.$title,'title_my'=>'နမူနာ လမ်းညွှန်: '.$my,'summary'=>'Draft learning topic for CMS training. Technical review is still required.','summary_my'=>'CMS စမ်းသပ်အသုံးပြုရန် အကြောင်းအရာ မူကြမ်းဖြစ်ပါသည်။ နည်းပညာစစ်ဆေးမှု လိုအပ်သေးပါသည်။',
        'description'=>'<p><strong>Demo editorial draft — not technical advice.</strong></p><p>Use this space for a qualified reviewer to explain '.strtolower($title).'. Include sources and the relevant vehicle-manufacturer requirements. Do not add unconfirmed product approvals or performance claims.</p>',
        'description_my'=>'<p>စမ်းသပ်အသုံးပြုရန် မူကြမ်းဖြစ်ပြီး နည်းပညာအကြံပြုချက် မဟုတ်ပါ။ အရည်အချင်းပြည့်မီသူ၏ စစ်ဆေးမှုနှင့် အတည်ပြုအချက်အလက်များ လိုအပ်ပါသည်။</p>',
        'kind'=>'knowledge','topic'=>$topic,'reviewer'=>'','related_product_id'=>$seed->id('products','demo.product.'.$product.'.1'),'image'=>null,'image_alt'=>'','meta_description'=>'Draft sample article. Review before publication.','sort_order'=>$order,'published'=>0,'updated_at'=>$seed->time('-'.($order+1).' days')
    ]);
    foreach(['packaging'=>'Packaging update template','company'=>'Company announcement template'] as $key=>$title)$seed->put('articles','demo.news.'.$key,[
        'title'=>'Demo news: '.$title,'title_my'=>'နမူနာ သတင်း: '.$title,'summary'=>'An unpublished company-news template. Replace with an approved announcement.','summary_my'=>'မထုတ်ပြန်ရသေးသော သတင်းနမူနာ ဖြစ်ပါသည်။ အတည်ပြုကြေညာချက်ဖြင့် အစားထိုးပါ။',
        'description'=>'<p>Demo announcement only. Enter the approved event date, confirmed information and customer contact instructions before publishing. No packaging change or company event is being announced by this sample.</p>',
        'description_my'=>'<p>နမူနာကြေညာချက်သာ ဖြစ်ပါသည်။ အတည်ပြုရက်စွဲနှင့် သတင်းအချက်အလက်ကို ထည့်သွင်းပြီးမှ ထုတ်ပြန်ပါ။</p>',
        'kind'=>'news','topic'=>'Company announcements','reviewer'=>'','related_product_id'=>null,'image'=>null,'image_alt'=>'','meta_description'=>'','sort_order'=>10,'published'=>0,'updated_at'=>$seed->time('-2 days')
    ]);
};
