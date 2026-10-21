<?php
namespace Superking\Seeders;

use Mini\Database as DB;

final class SeedContext
{
    public array $counts=[];
    public ?string $initialPassword=null;
    private \DateTimeImmutable $now;

    public function __construct() { $this->now=new \DateTimeImmutable(); }

    public function put(string $table,string $key,array $data,?array $naturalKey=null): int
    {
        if(!preg_match('/^[a-z_]+$/',$table))throw new \InvalidArgumentException('Invalid seed table.');
        $this->counts[$table]??=['created'=>0,'preserved'=>0];
        $row=DB::fetch("SELECT id FROM $table WHERE seed_key=?",[$key]);
        if(!$row&&$naturalKey){
            $clauses=[];foreach(array_keys($naturalKey) as $column){if(!preg_match('/^[a-z_]+$/',$column))throw new \InvalidArgumentException('Invalid seed key.');$clauses[]="$column=?";}
            $row=DB::fetch("SELECT id,seed_key FROM $table WHERE ".implode(' AND ',$clauses),array_values($naturalKey));
            if($row&&$row['seed_key']===null)DB::update($table,(int)$row['id'],['seed_key'=>$key]);
        }
        if($row){$this->counts[$table]['preserved']++;return (int)$row['id'];}
        $image=\App\Content\DemoContent::image($table,$key);
        if($image&&empty($data['image'])){
            $data['image']=$image;
            if(array_key_exists('image_alt',$data))$data['image_alt']='Illustrative stock application photo; not SUPERKING packaging or premises.';
        }
        $id=DB::insert($table,$data+['seed_key'=>$key]);$this->counts[$table]['created']++;return $id;
    }

    public function id(string $table,string $key): int
    {
        if(!preg_match('/^[a-z_]+$/',$table))throw new \InvalidArgumentException('Invalid seed table.');
        $id=DB::fetchValue("SELECT id FROM $table WHERE seed_key=?",[$key]);
        if(!$id)throw new \RuntimeException("Missing seed dependency: $table / $key");
        return (int)$id;
    }

    public function time(string $relative='now'): string { return $this->now->modify($relative)->format('Y-m-d H:i:s'); }
    public function adminId(): int { return (int)DB::fetchValue('SELECT id FROM admins ORDER BY id LIMIT 1'); }
}
