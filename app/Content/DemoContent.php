<?php
namespace App\Content;

use Mini\Database as DB;

final class DemoContent
{
    public const TABLES=['products','slides','company_sections','contacts','articles','manufacturing_updates','dealers','documents'];

    public static function image(string $table,string $key): ?string
    {
        if(!str_starts_with($key,'demo.'))return null;
        $range=match($table){
            'products'=>explode('.',$key)[2]??'passenger',
            'articles'=>match(explode('.',$key)[2]??''){'motorcycle','fundamentals'=>'motorcycle','heavy-duty','conditions'=>'heavy-duty','other'=>'industrial','faq'=>'transmission',default=>'passenger'},
            'manufacturing_updates'=>'industrial',
            'slides'=>str_ends_with($key,'distributor')?'heavy-duty':'motorcycle',
            'company_sections'=>str_ends_with($key,'distribution')?'heavy-duty':'passenger',
            default=>null
        };
        return $range?'assets/images/applications/'.$range.'.webp':null;
    }

    public static function publish(): array
    {
        return DB::transaction(static function(): array {
            $counts=[];
            foreach(self::TABLES as $table){
                $rows=DB::fetchAll("SELECT * FROM $table WHERE LEFT(seed_key,5)='demo.'");
                foreach($rows as $row){
                    $data=['published'=>1];
                    $image=self::image($table,$row['seed_key']);
                    if($image&&empty($row['image'])){
                        $data['image']=$image;
                        if(array_key_exists('image_alt',$row))$data['image_alt']='Illustrative stock application photo; not SUPERKING packaging or premises.';
                    }
                    DB::update($table,(int)$row['id'],$data);
                }
                $counts[$table]=count($rows);
            }
            return $counts;
        });
    }
}
