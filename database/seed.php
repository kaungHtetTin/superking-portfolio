<?php

use Mini\Database as DB;
if (!DB::fetch('SELECT id FROM settings WHERE id=1')) DB::insert('settings',['id'=>1,'company_name'=>'Super King','tagline'=>'Oils & lubricants','theme_color'=>'#b52025','intro'=>'Demo company profile: Super King is an oil and lubricant company. Replace this introduction with your company story, manufacturing capabilities and service information in the admin dashboard.','carousel_autoplay'=>0]);
if (!(int)DB::fetchValue('SELECT COUNT(*) FROM categories')) {
    $categories=[['Automotive oils','Explore engine oils for passenger vehicles.'],['Heavy-duty oils','Explore lubricants for commercial vehicles and fleets.'],['Industrial lubricants','Explore oils for industrial equipment and machinery.'],['Greases & specialties','Explore greases and specialist lubrication products.']];
    foreach($categories as $i=>[$name,$description]) {
        $id=DB::insert('categories',compact('name','description')+['sort_order'=>$i]);
        foreach(['Demo '.$name.' 01','Demo '.$name.' 02'] as $j=>$product) DB::insert('products',['category_id'=>$id,'name'=>$product,'summary'=>'Illustrative catalogue entry. Replace with verified product specifications and application information.','image'=>null,'benefits'=>'<p><strong>Demo product information</strong></p><ul><li>Add verified product features here.</li><li>Add documented benefits here.</li></ul>','applications'=>'<p>Replace this demo text with the intended applications for this product. Refer to the manufacturer’s documentation before use.</p>','published'=>1,'featured'=>$j===0?1:0,'updated_at'=>date('Y-m-d H:i:s')]);
    }
}
if (!(int)DB::fetchValue('SELECT COUNT(*) FROM slides')) {
    DB::insert('slides',['title'=>'Built to keep your world moving.','description'=>'Discover our oils and lubricants for automotive, commercial and industrial applications. Explore the range and find your next solution.','image'=>null,'button_text'=>'Explore our products','button_url'=>'/products','sort_order'=>0,'published'=>1]);
    DB::insert('slides',['title'=>'The right information. The right choice.','description'=>'Browse products by category and access product data sheets and safety documentation in one place.','image'=>null,'button_text'=>'Browse the catalogue','button_url'=>'/products','sort_order'=>1,'published'=>1]);
}
if (!(int)DB::fetchValue('SELECT COUNT(*) FROM company_sections')) {
    DB::insert('company_sections',['title'=>'Our company','description'=>'<p><strong>Demo company information.</strong> Add your company history, factory capabilities, values and services here using the admin editor.</p>','image'=>null,'sort_order'=>0,'published'=>1]);
    DB::insert('company_sections',['title'=>'Our approach','description'=>'<p>Replace this demo content with your approach to manufacturing, quality assurance and customer support. Include only verified certifications and claims.</p>','image'=>null,'sort_order'=>1,'published'=>1]);
}
if (!(int)DB::fetchValue('SELECT COUNT(*) FROM contacts')) DB::insert('contacts',['title'=>'Company enquiries — demo','email'=>'info@example.com','phone'=>'','address'=>'Replace this demo address with your office or factory location.','hours'=>'Add your company opening hours in the admin dashboard.','sort_order'=>0,'published'=>1]);
if (!(int)DB::fetchValue('SELECT COUNT(*) FROM admins')) {
    $password=bin2hex(random_bytes(10));
    DB::insert('admins',['email'=>'admin@superking.local','password'=>password_hash($password,PASSWORD_DEFAULT)]);
    echo "Admin email: admin@superking.local\nInitial password: $password\nChange these in Admin > Your account.\n";
}
echo "Seed complete. Existing records were preserved.\n";
