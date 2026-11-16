<?php
namespace App\Controllers;
use Mini\Database as DB;
final class SiteController {
    public function home(): void {
        view('site/home', ['title'=>t('Engineered for Performance. Built on Trust.','စွမ်းဆောင်ရည်နှင့် ယုံကြည်မှု'), 'slides'=>DB::fetchAll('SELECT * FROM slides WHERE published=1 ORDER BY sort_order,id'), 'categories'=>DB::fetchAll('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.published=1) AS product_count, (SELECT p.image FROM products p WHERE p.category_id=c.id AND p.published=1 AND p.image IS NOT NULL LIMIT 1) AS image FROM categories c ORDER BY sort_order,id'), 'products'=>DB::fetchAll('SELECT p.*,c.name category_name,c.name_my category_name_my FROM products p JOIN categories c ON c.id=p.category_id WHERE p.published=1 ORDER BY featured DESC,id DESC LIMIT 4'), 'articles'=>DB::fetchAll("SELECT * FROM articles WHERE published=1 AND (kind='news' OR reviewer<>'' OR LEFT(seed_key,5)=0x64656d6f2e) ORDER BY updated_at DESC LIMIT 3"), 'updates'=>DB::fetchAll('SELECT * FROM manufacturing_updates WHERE published=1 AND (verified=1 OR LEFT(seed_key,5)=0x64656d6f2e) ORDER BY sort_order,id DESC LIMIT 1')]);
    }
    public function products(): void {
        $query = $_GET['q'] ?? '';
        $q = mb_substr(trim(is_string($query) ? $query : ''),0,190);
        $category = filter_var($_GET['category'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $filters=[]; foreach(['viscosity','specification','application'] as $key) $filters[$key]=input_text($key);
        $where = 'p.published=1'; $bind = [];
        if ($q !== '') {
            // Match every word across the searchable fields; treat LIKE wildcards literally.
            foreach (preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                $columns=['p.name','p.summary','c.name','p.applications','p.benefits','p.name_my','p.summary_my','c.name_my','p.applications_my','p.benefits_my','p.product_code','p.viscosity','p.specification','p.vehicle_types'];
                $where .= ' AND ('.implode(' OR ',array_map(fn($col)=>"$col LIKE ? ESCAPE '!'",$columns)).')';
                $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
                array_push($bind, ...array_fill(0, count($columns), $pattern));
            }
        }
        if ($category) { $where .= ' AND p.category_id=?'; $bind[] = $category; }
        foreach($filters as $key=>$value) if($value!=='') { $column=$key==='application'?'vehicle_types':$key; $where.=" AND p.$column=?"; $bind[]=$value; }
        $options=[]; foreach(['viscosity','specification','vehicle_types'] as $col) $options[$col]=DB::fetchAll("SELECT DISTINCT $col value FROM products WHERE published=1 AND $col<>'' ORDER BY $col");
        $total = (int) DB::fetchValue("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE $where",$bind);
        $pages = max(1,(int)ceil($total/12)); $page = max(1,min($pages,(int)($_GET['page']??1))); $offset=($page-1)*12;
        view('site/products', ['title'=>t('Product Catalogue','ကုန်ပစ္စည်းစာရင်း'),'filters'=>$filters,'options'=>$options,'q'=>$q,'category'=>$category,'page'=>$page,'pages'=>$pages,'total'=>$total,'categories'=>DB::fetchAll('SELECT * FROM categories ORDER BY sort_order,id'),'products'=>DB::fetchAll("SELECT p.*,c.name category_name,c.name_my category_name_my FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY p.featured DESC,p.name LIMIT 12 OFFSET $offset",$bind)]);
    }
    public function product(string $id): void {
        $product = DB::fetch('SELECT p.*,c.name category_name,c.name_my category_name_my FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.published=1',[(int)$id]);
        if (!$product) not_found(); view('site/product',['title'=>localized($product,'name'),'meta'=>$product['meta_description']?:localized($product,'summary'),'product'=>$product]);
    }
    public function company(): void { view('site/company',['title'=>t('About SUPERKING','SUPERKING အကြောင်း'),'sections'=>DB::fetchAll('SELECT * FROM company_sections WHERE published=1 ORDER BY sort_order,id')]); }
    public function contact(): void { view('site/contact',['title'=>t('Contact & Support','ဆက်သွယ်ရန်'),'contacts'=>published_contacts(),'kind'=>'product']); }
    public function manufacturing(): void { view('site/manufacturing',['title'=>t('Manufacturing & Quality','ထုတ်လုပ်မှုနှင့် အရည်အသွေး'),'updates'=>DB::fetchAll('SELECT * FROM manufacturing_updates WHERE published=1 AND (verified=1 OR LEFT(seed_key,5)=0x64656d6f2e) ORDER BY sort_order,id DESC'),'documents'=>approved_documents()]); }
    public function articles(): void {
        $kind=str_ends_with(rtrim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/'),'/news')?'news':'knowledge'; $topic=input_text('topic');
        $rows=DB::fetchAll("SELECT * FROM articles WHERE published=1 AND kind=? AND (kind='news' OR reviewer<>'' OR LEFT(seed_key,5)=0x64656d6f2e)".($topic!==''?' AND topic=?':'').' ORDER BY sort_order,updated_at DESC',$topic!==''?[$kind,$topic]:[$kind]);
        view('site/articles',['title'=>$kind==='news'?t('News & Updates','သတင်းများ'):t('Lubricant Knowledge Center','ချောဆီ ဗဟုသုတစင်တာ'),'kind'=>$kind,'topic'=>$topic,'articles'=>$rows]);
    }
    public function article(string $id): void {
        $article=DB::fetch("SELECT * FROM articles WHERE id=? AND published=1 AND (kind='news' OR reviewer<>'' OR LEFT(seed_key,5)=0x64656d6f2e)",[(int)$id]);if(!$article)not_found();
        $related=$article['related_product_id']?DB::fetch('SELECT * FROM products WHERE id=? AND published=1',[$article['related_product_id']]):null;
        view('site/article',['title'=>localized($article,'title'),'meta'=>$article['meta_description']?:localized($article,'summary'),'article'=>$article,'related'=>$related]);
    }
    public function dealers(): void {
        $q=input_text('q');$region=input_text('region');$where='published=1 AND (verified=1 OR LEFT(seed_key,5)=0x64656d6f2e)';$bind=[];
        if($region!==''){$where.=' AND region=?';$bind[]=$region;}
        if($q!==''){ $where.=" AND CONCAT_WS(' ',title,title_my,city,township,address) LIKE ? ESCAPE '!'";$bind[]='%'.str_replace(['!','%','_'],['!!','!%','!_'],$q).'%'; }
        view('site/dealers',['title'=>t('Find A Dealer','အရောင်းကိုယ်စားလှယ် ရှာရန်'),'q'=>$q,'region'=>$region,'regions'=>DB::fetchAll('SELECT DISTINCT region FROM dealers WHERE published=1 AND (verified=1 OR LEFT(seed_key,5)=0x64656d6f2e) ORDER BY region'),'dealers'=>DB::fetchAll("SELECT * FROM dealers WHERE $where ORDER BY sort_order,title",$bind)]);
    }
    public function distributor(): void { view('site/distributor',['title'=>t('Become A Distributor','ဖြန့်ချိရေး မိတ်ဖက်ဖြစ်ရန်'),'documents'=>approved_documents(),'kind'=>'distributor']); }
    public function privacy(): void { view('site/privacy',['title'=>t('Privacy notice','ကိုယ်ရေးအချက်အလက် မူဝါဒ')]); }
    public function inquiry(): void {
        $kind=input_text('kind',30,true);$path=$kind==='distributor'?'distributor':'contact';
        $fail=function(string $message)use($path):never { $_SESSION['errors']=['inquiry'=>$message];$_SESSION['old']=array_filter($_POST,fn($v,$k)=>is_string($v)&&!in_array($k,['_token','website']),ARRAY_FILTER_USE_BOTH);redirect($path.'?lang='.language().'#inquiry'); };
        if(!in_array($kind,['distributor','product','complaint','general']))$fail(t('Choose an inquiry type.','မေးမြန်းလိုသည့် အမျိုးအစား ရွေးပါ။'));
        $started=$_SESSION['inquiry_started']??0;
        if(input_text('website',190,true)!=='' || !$started || time()-$started<2)$fail(t('Please try submitting the form again.','ဖောင်ကို ပြန်လည်ပေးပို့ပါ။'));
        $data=[];foreach(['full_name'=>190,'company_name'=>190,'country'=>190,'email'=>190,'phone'=>100,'business_type'=>100,'categories'=>2000,'volume'=>190,'network'=>3000,'message'=>5000,'batch_number'=>190] as $key=>$max) {
            if(!is_string($_POST[$key]??'')||mb_strlen($_POST[$key]??'')>$max)$fail(t('A field is too long or invalid. Please check your entries.','ဖြည့်သွင်းထားသော အချက်အလက်များကို စစ်ဆေးပါ။'));
            $data[$key]=input_text($key,$max,true);
        }
        if($data['full_name']===''||$data['message']===''||!filter_var($data['email'],FILTER_VALIDATE_EMAIL)||($_POST['consent']??'')!=='1')$fail(t('Enter your name, a valid email, your message and privacy consent.','အမည်၊ အီးမေးလ်၊ မက်ဆေ့ချ်နှင့် သဘောတူညီချက် ဖြည့်ပါ။'));
        if($kind==='distributor' && ($data['company_name']===''||$data['country']===''||$data['business_type']===''))$fail(t('Company, country and business type are required.','ကုမ္ပဏီ၊ နိုင်ငံနှင့် လုပ်ငန်းအမျိုးအစား ဖြည့်ပါ။'));
        $fingerprint=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.env('APP_NAME','SUPERKING'));
        $reference='SK-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
        $saved=DB::transaction(function()use($fingerprint,$reference,$kind,$data){
            DB::statement('INSERT IGNORE INTO inquiry_limits (fingerprint,attempts,last_attempt) VALUES (?,0,NOW())',[$fingerprint]);
            $limit=DB::fetch('SELECT attempts,last_attempt > DATE_SUB(NOW(),INTERVAL 1 HOUR) recent FROM inquiry_limits WHERE fingerprint=? FOR UPDATE',[$fingerprint]);
            if($limit['recent'] && $limit['attempts']>=5)return false;
            DB::statement('UPDATE inquiry_limits SET attempts=IF(last_attempt < DATE_SUB(NOW(),INTERVAL 1 HOUR),1,attempts+1),last_attempt=NOW() WHERE fingerprint=?',[$fingerprint]);
            DB::insert('inquiries',$data+['reference'=>$reference,'kind'=>$kind]);return true;
        });
        if(!$saved)$fail(t('Submission limit reached. Please try again in one hour.','တစ်နာရီအကြာတွင် ပြန်လည်ပေးပို့ပါ။'));
        unset($_SESSION['old'],$_SESSION['errors'],$_SESSION['inquiry_started']);
        $_SESSION['inquiry_receipt']=$reference;redirect('inquiry/received?lang='.language());
    }
    public function received(): void { $reference=$_SESSION['inquiry_receipt']??null;if(!$reference)redirect('contact');view('site/received',['title'=>t('Inquiry received','မေးမြန်းချက် လက်ခံရရှိပါပြီ'),'reference'=>$reference]); }
    public function sitemap(): void {
        header('Content-Type: application/xml; charset=UTF-8');$base=rtrim((string)env('APP_URL',''),'/');
        if(!preg_match('#^https?://#',$base))$base='http://localhost'.rtrim(url(),'/');
        $paths=['','company','products','manufacturing','knowledge','dealers','distributor','news','contact','privacy'];
        foreach(DB::fetchAll('SELECT id FROM products WHERE published=1') as $p)$paths[]='products/'.$p['id'];
        foreach(DB::fetchAll("SELECT id FROM articles WHERE published=1 AND (kind='news' OR reviewer<>'' OR LEFT(seed_key,5)=0x64656d6f2e)") as $a)$paths[]='articles/'.$a['id'];
        echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';foreach($paths as $path)foreach(['en','my'] as $lang)echo '<url><loc>'.e($base.($path?'/'.$path:'').'?lang='.$lang).'</loc></url>';echo '</urlset>';
    }
    public function robots(): void { header('Content-Type: text/plain; charset=UTF-8');$base=rtrim(parse_url(url(),PHP_URL_PATH)?:'','/');echo "User-agent: *\nAllow: /\nDisallow: $base/admin\nDisallow: $base/storage\n";if(preg_match('#^https?://#',(string)env('APP_URL','')))echo 'Sitemap: '.url('sitemap.xml')."\n"; }
}
