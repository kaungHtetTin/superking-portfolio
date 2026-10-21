<?php
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    $ranges=[
        'motorcycle'=>['Motorcycle Oil','မော်တော်ဆိုင်ကယ် ချောဆီ','Motorcycle',['10W-40','20W-50','10W-30']],
        'passenger'=>['Passenger Car Oil','လူစီးကား ချောဆီ','Passenger car',['5W-30','10W-40','20W-50']],
        'heavy-duty'=>['Heavy-Duty Oil','လုပ်ငန်းသုံးယာဉ် ချောဆီ','Commercial vehicle',['15W-40','20W-50','10W-40']],
        'industrial'=>['Industrial Lubricant','စက်မှုလုပ်ငန်းသုံး ချောဆီ','Industrial equipment',['Sample hydraulic grade','Sample gear grade','Sample equipment grade']],
        'transmission'=>['Transmission / Brake Fluid','ဂီယာနှင့် ဘရိတ်ဆီ','Transmission / brake system',['Sample ATF','Sample brake fluid','Sample transmission oil']]
    ];
    foreach($ranges as $category=>[$name,$nameMy,$vehicle,$grades])foreach($grades as $i=>$grade){
        $label='Demo '.$name.' — '.$grade;
        $seed->put('products','demo.product.'.$category.'.'.($i+1),[
            'category_id'=>$seed->id('categories','category.'.$category),'name'=>$label,'name_my'=>'နမူနာ '.$nameMy.' — '.$grade,
            'product_code'=>'DEMO-'.strtoupper(str_replace('-','',$category)).'-'.sprintf('%03d',$i+1),
            'summary'=>'Demonstration catalogue record only. Grades, sizes and applications are illustrative and are not approved SUPERKING specifications.',
            'summary_my'=>'စမ်းသပ်အသုံးပြုရန် နမူနာသာ ဖြစ်ပါသည်။ ဖော်ပြထားသော ပျစ်နှုန်း၊ အရွယ်အစားနှင့် အသုံးပြုမှုများသည် SUPERKING ၏ အတည်ပြုသတ်မှတ်ချက်များ မဟုတ်ပါ။',
            'benefits'=>'<p><strong>Demo content — review required.</strong> Replace this entry with the official product description and documented characteristics before publication.</p>',
            'benefits_my'=>'<p>နမူနာအကြောင်းအရာသာ ဖြစ်ပါသည်။ မထုတ်ပြန်မီ တရားဝင်ကုန်ပစ္စည်း အချက်အလက်ဖြင့် အစားထိုးပါ။</p>',
            'applications'=>'<p>Training example for '.$vehicle.'. Confirm vehicle-manufacturer requirements and approved product documentation before making any recommendation.</p>',
            'applications_my'=>'<p>စမ်းသပ်အသုံးပြုရန်သာ ဖြစ်ပါသည်။ ယာဉ်ထုတ်လုပ်သူ၏ လမ်းညွှန်ချက်နှင့် အတည်ပြုစာရွက်စာတမ်းကို စစ်ဆေးပါ။</p>',
            'viscosity'=>in_array($category,['motorcycle','passenger','heavy-duty'])?$grade:'','specification'=>'','vehicle_types'=>$vehicle,'package_sizes'=>'Sample only: 1 L / 4 L / 20 L',
            'storage_info'=>'Demo: replace with the approved product storage and handling instructions.','image'=>null,'data_sheet'=>null,'safety_sheet'=>null,'image_alt'=>'',
            'meta_description'=>'Demo product entry for CMS testing. Not approved for publication.','published'=>0,'featured'=>$i===0?1:0,'updated_at'=>$seed->time('-'.($i+1).' days')
        ]);
    }
};
