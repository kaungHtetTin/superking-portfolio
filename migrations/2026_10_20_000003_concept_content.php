<?php
// Adapt only untouched starter categories. Staff changes are never overwritten.
$up=[];
$categories=[
    ['Automotive oils','Explore engine oils for passenger vehicles.','Passenger Car','Petrol and diesel engine oils.','လူစီးကား ချောဆီများ','ဓာတ်ဆီနှင့် ဒီဇယ်အင်ဂျင်ဝိုင်များ။',1],
    ['Heavy-duty oils','Explore lubricants for commercial vehicles and fleets.','Heavy-Duty Diesel','Trucks, buses and commercial vehicles.','အကြီးစား ဒီဇယ်ချောဆီများ','ကုန်တင်ယာဉ်၊ ဘတ်စ်ကားနှင့် လုပ်ငန်းသုံးယာဉ်များ။',2],
    ['Industrial lubricants','Explore oils for industrial equipment and machinery.','Hydraulic & Gear','Industrial equipment and gear applications.','ဟိုက်ဒရောလစ်နှင့် ဂီယာဝိုင်','စက်မှုလုပ်ငန်းသုံး စက်ပစ္စည်းနှင့် ဂီယာများ။',3],
    ['Greases & specialties','Explore greases and specialist lubrication products.','ATF & Brake Fluid','Transmission and brake-fluid products.','ATF နှင့် ဘရိတ်ဆီ','ဂီယာနှင့် ဘရိတ်စနစ်သုံး ဆီများ။',4]
];
foreach($categories as [$old,$oldDescription,$name,$description,$my,$myDescription,$order]) {
    $up[]="UPDATE categories SET name='$name',description='$description',name_my='$my',description_my='$myDescription',sort_order=$order WHERE name='$old' AND description='$oldDescription' AND NOT EXISTS (SELECT id FROM (SELECT id FROM categories WHERE name='$name') existing_category)";
}
$up[]="INSERT INTO categories (name,description,name_my,description_my,sort_order) SELECT 'Motorcycle Oils','4-stroke and 2-stroke motorcycle lubricants.','မော်တော်ဆိုင်ကယ် ချောဆီများ','လေးချက်ထိုးနှင့် နှစ်ချက်ထိုး မော်တော်ဆိုင်ကယ်များအတွက်။',0 WHERE NOT EXISTS (SELECT id FROM categories WHERE name='Motorcycle Oils')";
$up[]="UPDATE settings SET intro_my='SUPERKING ချောဆီများကို ၂၀၁၆ ခုနှစ်မှစ၍ ရွိုင်ရယ် မြန်မာကင်း ကုမ္ပဏီလီမိတက်က ယူအေအီးမှ တင်သွင်းဖြန့်ချိခဲ့ပါသည်။ ယခု မြန်မာနိုင်ငံတွင် ပြည်တွင်းထုတ်လုပ်မှုအတွက် ပြင်ဆင်နေပြီး ကုန်ပစ္စည်းအရည်အသွေး၊ ဖောက်သည်တန်ဖိုးနှင့် အားကိုးရသော ဝန်ဆောင်မှုတို့ကို ရေရှည်ဦးစားပေးထားပါသည်။' WHERE intro_my IS NULL AND intro LIKE 'SUPERKING LUBRICANTS has been imported from the UAE%'";
return ['up'=>$up,'down'=>[]];
