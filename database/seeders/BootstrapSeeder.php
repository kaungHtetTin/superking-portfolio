<?php
use Mini\Database as DB;
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    if(!DB::fetch('SELECT id FROM settings WHERE id=1')) {
        DB::insert('settings',['id'=>1,'company_name'=>'SUPERKING LUBRICANTS','tagline'=>'Driven by Quality','theme_color'=>'#C1121F',
            'intro'=>'SUPERKING LUBRICANTS has been imported from the UAE and distributed in Myanmar since 2016 by Royal Myanmar King Company Limited. We are now preparing to establish local manufacturing in Myanmar, with a long-term commitment to product quality, customer value and dependable support.',
            'intro_my'=>'SUPERKING ချောဆီများကို ၂၀၁၆ ခုနှစ်မှစ၍ ရွိုင်ရယ် မြန်မာကင်း ကုမ္ပဏီလီမိတက်က ယူအေအီးမှ တင်သွင်းဖြန့်ချိခဲ့ပါသည်။ ယခု မြန်မာနိုင်ငံတွင် ပြည်တွင်းထုတ်လုပ်မှုအတွက် ပြင်ဆင်နေပါသည်။','carousel_autoplay'=>0]);
        $seed->counts['settings']=['created'=>1,'preserved'=>0];
    } else $seed->counts['settings']=['created'=>0,'preserved'=>1];

    $categories=[
        ['motorcycle','Motorcycle Oils','4-stroke and 2-stroke motorcycle lubricants.','မော်တော်ဆိုင်ကယ် ချောဆီများ','လေးချက်ထိုးနှင့် နှစ်ချက်ထိုး မော်တော်ဆိုင်ကယ်များအတွက်။'],
        ['passenger','Passenger Car','Petrol and diesel engine oils.','လူစီးကား ချောဆီများ','ဓာတ်ဆီနှင့် ဒီဇယ်အင်ဂျင်ဝိုင်များ။'],
        ['heavy-duty','Heavy-Duty Diesel','Trucks, buses and commercial vehicles.','အကြီးစား ဒီဇယ်ချောဆီများ','ကုန်တင်ယာဉ်၊ ဘတ်စ်ကားနှင့် လုပ်ငန်းသုံးယာဉ်များ။'],
        ['industrial','Hydraulic & Gear','Industrial equipment and gear applications.','ဟိုက်ဒရောလစ်နှင့် ဂီယာဝိုင်','စက်မှုလုပ်ငန်းသုံး စက်ပစ္စည်းနှင့် ဂီယာများ။'],
        ['transmission','ATF & Brake Fluid','Transmission and brake-fluid products.','ATF နှင့် ဘရိတ်ဆီ','ဂီယာနှင့် ဘရိတ်စနစ်သုံး ဆီများ။']
    ];
    foreach($categories as $order=>[$key,$name,$description,$nameMy,$descriptionMy])$seed->put('categories','category.'.$key,['name'=>$name,'description'=>$description,'name_my'=>$nameMy,'description_my'=>$descriptionMy,'sort_order'=>$order],['name'=>$name]);

    if(!(int)DB::fetchValue('SELECT COUNT(*) FROM admins')) {
        // Preserve the project's existing local default; deployments can supply an override.
        $password=(string)env('SEED_ADMIN_PASSWORD','password');
        if($password==='')throw new \InvalidArgumentException('SEED_ADMIN_PASSWORD must not be empty.');
        DB::insert('admins',['email'=>'admin@superking.local','password'=>password_hash($password,PASSWORD_DEFAULT)]);
        $seed->initialPassword=$password;$seed->counts['admins']=['created'=>1,'preserved'=>0];
    } else $seed->counts['admins']=['created'=>0,'preserved'=>(int)DB::fetchValue('SELECT COUNT(*) FROM admins')];
};
