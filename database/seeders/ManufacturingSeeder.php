<?php
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    foreach([
        ['planning','Local manufacturing project planning','ပြည်တွင်းထုတ်လုပ်ရေး စီမံကိန်းအစီအစဉ်','planned'],
        ['installation','Installation progress template','တပ်ဆင်မှု တိုးတက်ရေး နမူနာ','installation'],
        ['commissioning','Commissioning checklist template','စမ်းသပ်လည်ပတ်မှု စစ်ဆေးစာရင်း နမူနာ','commissioning']
    ] as $order=>[$key,$title,$my,$stage])$seed->put('manufacturing_updates','demo.manufacturing.'.$key,[
        'title'=>'Demo: '.$title,'title_my'=>'နမူနာ: '.$my,
        'description'=>'<p>Unverified planning example for staff training. This record does not confirm installation, commissioning or production. Add actual milestone dates, approved facility photographs and company verification before publishing.</p>',
        'description_my'=>'<p>ဝန်ထမ်းများ စမ်းသပ်အသုံးပြုရန် အတည်မပြုရသေးသော နမူနာ ဖြစ်ပါသည်။ တပ်ဆင်မှု၊ စမ်းသပ်လည်ပတ်မှု သို့မဟုတ် ထုတ်လုပ်မှု ပြီးစီးကြောင်း မဆိုလိုပါ။ အမှန်တကယ်တိုးတက်မှုကို အတည်ပြုပြီးမှ ထုတ်ပြန်ပါ။</p>',
        'stage'=>$stage,'image'=>null,'image_alt'=>'','verified'=>0,'sort_order'=>$order,'published'=>0,'updated_at'=>$seed->time('-'.($order+2).' days')
    ]);
};
