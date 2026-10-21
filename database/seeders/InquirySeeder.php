<?php
use Superking\Seeders\SeedContext;

return static function(SeedContext $seed): void {
    $examples=[
        ['product','new','Myanmar','Motorcycle Oils'],['distributor','new','Thailand','Passenger Car'],
        ['complaint','new','Myanmar','Heavy-Duty Diesel'],['general','in_progress','Myanmar','Hydraulic & Gear'],
        ['distributor','in_progress','United Arab Emirates','Motorcycle Oils, Passenger Car'],
        ['product','resolved','Myanmar','ATF & Brake Fluid'],['complaint','resolved','Myanmar','Passenger Car'],
        ['distributor','in_progress','Myanmar','Heavy-Duty Diesel, Hydraulic & Gear']
    ];
    foreach($examples as $i=>[$kind,$status,$country,$categories]){
        $number=sprintf('%03d',$i+1);$business=$kind==='distributor';
        $seed->put('inquiries','demo.inquiry.'.$number,[
            'reference'=>'DEMO-INQ-'.$number,'kind'=>$kind,'full_name'=>'Demo '.($business?'Business Contact ':'Customer ').$number,
            'company_name'=>$business?'Demo Distribution Business '.$number:'','country'=>$country,'email'=>'demo.contact'.$number.'@example.invalid','phone'=>'',
            'business_type'=>$business?($i%2?'Importer':'Distributor'):'','categories'=>$categories,'volume'=>$business?'Demo estimate: discuss requirements':'',
            'network'=>$business?'Demo network description for staff training; no actual business is represented.':'',
            'message'=>'DEMONSTRATION INQUIRY ONLY. Sample '.$kind.' workflow for '.$categories.'. No real customer submitted this message; do not contact anyone based on this record.',
            'batch_number'=>$kind==='complaint'?'DEMO-BATCH-'.$number:'','status'=>$status,
            'internal_notes'=>$status==='new'?'Demo record awaiting staff review. No customer action is required.':'Demo follow-up note. No real customer contact or resolution took place.',
            'created_at'=>$seed->time('-'.($i+1).' days'),'updated_at'=>$status==='new'?null:$seed->time('-'.$i.' days'),'handled_by'=>$status==='new'?null:$seed->adminId()
        ],['reference'=>'DEMO-INQ-'.$number]);
    }
};
