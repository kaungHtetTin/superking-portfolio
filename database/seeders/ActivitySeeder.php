<?php
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    $targets=[
        ['products','demo.product.motorcycle.1','Demo motorcycle product'],['slides','demo.banner.products','Demo product banner'],
        ['company_sections','company.history','Company background from the brief'],['contacts','demo.contact.support','Demo support contact'],
        ['articles','demo.knowledge.fundamentals','Demo knowledge article'],['manufacturing_updates','demo.manufacturing.planning','Demo manufacturing plan'],
        ['dealers','demo.dealer.yangon','Demo dealer entry'],['documents','demo.document.catalogue','Demo catalogue attachment'],
        ['inquiries','demo.inquiry.001','DEMO-INQ-001']
    ];
    foreach($targets as [$table,$key,$label])$seed->put('admin_activity','demo.activity.'.$table,[
        'actor'=>'Database seeder (demo)','action'=>'Seeded','content_type'=>$table,'record_id'=>$seed->id($table,$key),'record_label'=>$label,'created_at'=>$seed->time()
    ]);
};
