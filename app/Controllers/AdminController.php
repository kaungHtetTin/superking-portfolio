<?php
namespace App\Controllers;
use Mini\Database as DB;
final class AdminController {
    public const TYPES = ['products'=>'Products','categories'=>'Categories','slides'=>'Homepage banners','company_sections'=>'Company information','contacts'=>'Company contacts','articles'=>'Knowledge & news','manufacturing_updates'=>'Manufacturing updates','dealers'=>'Dealer directory','documents'=>'Approved documents'];
    private function type(string $type): void { if (!isset(self::TYPES[$type])) not_found(); }
    private function render(string $view,array $data=[]): void { view('admin/'.$view,$data,'layouts/admin'); }
    private function activityLog(string $action,string $type,?int $id,string $label): void {
        $actor=DB::fetchValue('SELECT email FROM admins WHERE id=?',[$_SESSION['admin_id']])?:'Administrator';
        DB::insert('admin_activity',['actor'=>$actor,'action'=>$action,'content_type'=>$type,'record_id'=>$id,'record_label'=>mb_substr($label,0,255)]);
    }
    private function returnPath(string $type): string {
        $path=input_text('return_to',2000,true)?:input_text('return_to',2000);
        return preg_match('#^admin/'.preg_quote($type,'#').'(?:\?[A-Za-z0-9%_=.&+\-]*)?$#',$path)?$path:'admin/'.$type;
    }
    private function integer(string $key,int $default=0): int {
        $value=$_POST[$key]??$default;
        if($value==='')return $default;
        if(!is_scalar($value)||filter_var($value,FILTER_VALIDATE_INT)===false||((int)$value)<0)throw new \InvalidArgumentException('Choose a valid '.str_replace('_',' ',$key).'.');
        return (int)$value;
    }
    private function like(string $text): string { return '%'.str_replace(['!','%','_'],['!!','!%','!_'],$text).'%'; }
    private function fail(array $errors, string $path): never {
        $_SESSION['errors']=$errors; $_SESSION['old']=array_filter($_POST,fn($v,$k)=>is_scalar($v)&&!str_contains($k,'password')&&$k!=='_token',ARRAY_FILTER_USE_BOTH); redirect($path);
    }
    public function login(): void { if (isset($_SESSION['admin_id'])) redirect('admin'); view('admin/login',['title'=>'Admin sign in'],null); }
    public function authenticate(): void {
        $email=input_text('email',190,true); $password=is_string($_POST['password']??null)?$_POST['password']:'';
        $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').'|'.mb_strtolower($email));
        $attempt=DB::fetch('SELECT attempts, last_attempt > DATE_SUB(NOW(),INTERVAL 15 MINUTE) AS recent FROM login_attempts WHERE fingerprint=?',[$key]);
        if ($attempt && $attempt['attempts']>=5 && $attempt['recent']) $this->fail(['login'=>'Too many attempts. Please try again in 15 minutes.'],'admin/login');
        $admin=DB::fetch('SELECT * FROM admins WHERE email=?',[$email]);
        if (!$admin || !password_verify($password,$admin['password'])) {
            DB::statement('INSERT INTO login_attempts (fingerprint,attempts,last_attempt) VALUES (?,1,NOW()) ON DUPLICATE KEY UPDATE attempts=IF(last_attempt < DATE_SUB(NOW(),INTERVAL 15 MINUTE),1,attempts+1),last_attempt=NOW()',[$key]);
            $this->fail(['login'=>'Email or password is incorrect.'],'admin/login');
        }
        DB::statement('DELETE FROM login_attempts WHERE fingerprint=?',[$key]); session_regenerate_id(true); $_SESSION['admin_id']=$admin['id']; redirect('admin');
    }
    public function logout(): void { $_SESSION=[]; session_regenerate_id(true); redirect('admin/login'); }
    public function dashboard(): void {
        $counts=[]; foreach(self::TYPES as $type=>$label) $counts[$type]=(int)DB::fetchValue("SELECT COUNT(*) FROM $type");
        $published=[];$drafts=[];foreach(self::TYPES as $type=>$label)if($type!=='categories'){
            $published[$type]=(int)DB::fetchValue("SELECT COUNT(*) FROM $type WHERE published=1");$drafts[$type]=$counts[$type]-$published[$type];
        }
        $inbox=DB::fetch('SELECT COUNT(*) total, COALESCE(SUM(status=\'new\'),0) fresh, COALESCE(SUM(status=\'in_progress\'),0) progress, COALESCE(SUM(status=\'resolved\'),0) resolved FROM inquiries');
        $this->render('dashboard',['title'=>'Overview','counts'=>$counts,'published'=>$published,'drafts'=>$drafts,'inbox'=>$inbox,
            'distribution'=>DB::fetch('SELECT COUNT(*) total,MAX(recorded_at) latest FROM lucky_locations'),
            'recent'=>DB::fetchAll('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id ORDER BY updated_at DESC,id DESC LIMIT 5'),
            'latestInquiries'=>DB::fetchAll("SELECT * FROM inquiries ORDER BY (status='new') DESC,created_at DESC,id DESC LIMIT 5"),
            'activity'=>DB::fetchAll('SELECT * FROM admin_activity ORDER BY id DESC LIMIT 6')]);
    }
    public function listing(string $type): void {
        $this->type($type);$q=input_text('q');$column=in_array($type,['products','categories'])?'name':'title';
        $where='1=1';$bind=[];$category=filter_var($_GET['category']??0,FILTER_VALIDATE_INT)?:0;
        if($q!==''){$columns=[$column,$column.'_my'];if($type==='products')$columns=array_merge($columns,['product_code','summary','specification']);elseif($type==='contacts')$columns=array_merge($columns,['email','phone','address']);elseif($type==='dealers')$columns=array_merge($columns,['region','city','township','address']);else $columns[]='description';$where.=' AND ('.implode(' OR ',array_map(fn($field)=>"$field LIKE ? ESCAPE '!'",$columns)).')';$bind=array_fill(0,count($columns),$this->like($q));}
        if($type==='products'&&$category){$where.=' AND category_id=?';$bind[]=$category;}
        $status=input_text('status',20);if($type!=='categories'&&in_array($status,['published','draft'])){$where.=' AND published=?';$bind[]=$status==='published'?1:0;}
        $verified=input_text('verified',10);if(in_array($type,['dealers','documents','manufacturing_updates'])&&in_array($verified,['yes','no'])){$where.=' AND verified=?';$bind[]=$verified==='yes'?1:0;}
        $kind=input_text('kind',40);$kindColumn=$type==='articles'?'kind':($type==='documents'?'document_type':null);if($kindColumn&&$kind!==''){$where.=" AND $kindColumn=?";$bind[]=$kind;}
        $sort=input_text('sort',20);$orders=['newest'=>'id DESC','oldest'=>'id ASC','name'=>"$column ASC,id ASC"];
        if($type==='products')$orders['updated']='updated_at DESC,id DESC';else $orders['display']='sort_order ASC,id ASC';
        if(!isset($orders[$sort]))$sort='newest';
        $total=(int)DB::fetchValue("SELECT COUNT(*) FROM $type WHERE $where",$bind); $pages=max(1,(int)ceil($total/20)); $page=max(1,min($pages,(int)($_GET['page']??1)));$offset=($page-1)*20;
        $filters=compact('q','category','status','verified','kind','sort','page');
        $this->render('list',['title'=>self::TYPES[$type],'type'=>$type,'filters'=>$filters,'sorts'=>array_keys($orders),'returnTo'=>'admin/'.$type.'?'.http_build_query($filters),'q'=>$q,'category'=>$category,'categories'=>DB::fetchAll('SELECT * FROM categories ORDER BY sort_order,name'),'rows'=>DB::fetchAll("SELECT * FROM $type WHERE $where ORDER BY {$orders[$sort]} LIMIT 20 OFFSET $offset",$bind),'total'=>$total,'pages'=>$pages,'page'=>$page]);
    }
    public function form(string $type,?string $id=null): void {
        $this->type($type); $row=$id?DB::fetch("SELECT * FROM $type WHERE id=?",[(int)$id]):[];if($id&&!$row)not_found();
        $this->render('form',['title'=>($id?'Edit ':'Add ').self::TYPES[$type],'type'=>$type,'row'=>$row,'returnTo'=>$this->returnPath($type),'categories'=>DB::fetchAll('SELECT * FROM categories ORDER BY sort_order,name'),'relatedProducts'=>$type==='articles'?DB::fetchAll('SELECT id,name,published FROM products ORDER BY name'):[]]);
    }
    private function settingsContext(): array {
        $returnTo=$this->returnPath('products');
        $raw=input_text('ids',2200);$ids=$raw!==''?explode(',',$raw):[];
        $hasPreview=count($ids)>0&&count($ids)<=200&&!array_filter($ids,fn($id)=>!preg_match('/^[1-9][0-9]{0,9}$/D',$id));
        $single=$hasPreview&&count($ids)===1&&input_text('mode',10)==='single';
        $previewPath=$returnTo;
        if($hasPreview)$previewPath=$single?'admin/products/'.$ids[0].'/qr?'.http_build_query(['return_to'=>$returnTo]):'admin/products/qr?'.http_build_query(['ids'=>implode(',',$ids),'return_to'=>$returnTo]);
        $settingsQuery=['return_to'=>$returnTo];
        if($hasPreview)$settingsQuery+=['ids'=>implode(',',$ids),'mode'=>$single?'single':'batch'];
        return compact('returnTo','previewPath','hasPreview','single','settingsQuery');
    }
    private function settingsPath(string $tab): string { return 'admin/settings?'.http_build_query(['tab'=>$tab]+$this->settingsContext()['settingsQuery']); }
    public function qrSettings(): void { redirect($this->settingsPath('qr')); }
    public function productQr(string $id): void {
        if(!ctype_digit($id)||(int)$id<1)not_found();
        $product=DB::fetch('SELECT id,name,product_code,published FROM products WHERE id=?',[(int)$id]);
        if(!$product)not_found();
        view('admin/product-qr',['title'=>'Print product QR code','products'=>[$product],'returnTo'=>$this->returnPath('products')],null);
    }
    public function productQrBatch(): void {
        $raw=$_GET['ids']??'';
        $ids=is_string($raw)&&strlen($raw)<=2200?explode(',',$raw):[];
        if(!$ids||count($ids)>200||array_filter($ids,fn($id)=>!preg_match('/^[1-9][0-9]{0,9}$/D',$id)))
            redirect($this->returnPath('products'),'Select between 1 and 200 products to print QR labels.','warning');
        $ids=array_values(array_unique(array_map('intval',$ids)));
        $rows=DB::fetchAll('SELECT id,name,product_code,published FROM products WHERE id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids);
        if(count($rows)!==count($ids))redirect($this->returnPath('products'),'Some selected products no longer exist. Clear the selection and select your products again.','warning');
        $byId=array_column($rows,null,'id');
        $products=array_map(fn($id)=>$byId[$id],$ids);
        view('admin/product-qr',['title'=>'Print product QR labels','products'=>$products,'batch'=>true,'returnTo'=>$this->returnPath('products')],null);
    }
    private function upload(string $field, string $kind, array &$newFiles): ?string {
        $file=$_FILES[$field]??null; if(!$file||$file['error']===UPLOAD_ERR_NO_FILE)return null;
        if($file['error']!==UPLOAD_ERR_OK)throw new \InvalidArgumentException('Upload failed. Check the file size and try again.');
        if($file['size']>($kind==='pdf'?10:5)*1024*1024)throw new \InvalidArgumentException('File is too large. Images: 5 MB maximum. PDFs: 10 MB maximum.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $allowed=$kind==='pdf'?['application/pdf'=>'pdf']:['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($allowed[$mime]))throw new \InvalidArgumentException($kind==='pdf'?'Please upload a PDF document.':'Please upload a JPG, PNG or WebP image.');
        if($kind!=='pdf'&&!getimagesize($file['tmp_name']))throw new \InvalidArgumentException('Invalid image.');
        if($kind==='pdf'&&file_get_contents($file['tmp_name'],false,null,0,5)!=='%PDF-')throw new \InvalidArgumentException('Invalid PDF document.');
        $path='uploads/'.bin2hex(random_bytes(16)).'.'.$allowed[$mime];
        if(!is_dir(BASE_PATH.'/public/uploads'))mkdir(BASE_PATH.'/public/uploads',0755,true);
        if(!move_uploaded_file($file['tmp_name'],BASE_PATH.'/public/'.$path))throw new \RuntimeException('Unable to save uploaded file.');
        $newFiles[]=$path;return $path;
    }
    private function cleanFiles(array $paths): void { foreach($paths as $path)if(is_string($path)&&preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#',$path)&&is_file(BASE_PATH.'/public/'.$path)&&!@unlink(BASE_PATH.'/public/'.$path))error_log('Unable to remove replaced upload: '.$path); }
    private function text(string $key,int $max=190): string { $v=$_POST[$key]??'';if(!is_string($v))throw new \InvalidArgumentException('Invalid field value.');if(mb_strlen($v)>$max)throw new \InvalidArgumentException(ucwords(str_replace('_',' ',$key))." must be $max characters or fewer.");return trim($v); }
    public function save(string $type): void {
        $this->type($type);try{$id=$this->integer('id');}catch(\InvalidArgumentException $e){$this->fail(['form'=>$e->getMessage()],'admin/'.$type.'/create');}
        $row=$id?DB::fetch("SELECT * FROM $type WHERE id=?",[$id]):[];if($id&&!$row)not_found();
        $returnTo=$this->returnPath($type);$path='admin/'.$type.($id?"/$id/edit":'/create').'?'.http_build_query(['return_to'=>$returnTo]);$newFiles=[];$removed=[];
        try {
            $key=in_array($type,['products','categories'])?'name':'title';$data=[$key=>$this->text($key)];
            if($data[$key]==='')throw new \InvalidArgumentException('A name or title is required.');
            if($type==='products') {
                $data+=['category_id'=>$this->integer('category_id'),'summary'=>$this->text('summary',500),'benefits'=>rich_text($this->text('benefits',100000)),'applications'=>rich_text($this->text('applications',100000)),'published'=>isset($_POST['published'])?1:0,'featured'=>isset($_POST['featured'])?1:0,'updated_at'=>date('Y-m-d H:i:s')];
                if(!DB::fetch('SELECT id FROM categories WHERE id=?',[$data['category_id']]))throw new \InvalidArgumentException('Please select an existing category.');
                foreach(['product_code','viscosity','specification','vehicle_types','package_sizes','image_alt','meta_description'] as $field)$data[$field]=$this->text($field,500);
                $data['storage_info']=$this->text('storage_info',10000);
            } elseif(in_array($type,['articles','manufacturing_updates','dealers','documents'])) {
                $data['description']=in_array($type,['articles','manufacturing_updates'])?rich_text($this->text('description',100000)):$this->text('description',10000);
                if($type==='articles') {
                    $data+=['kind'=>$this->text('kind',20),'topic'=>$this->text('topic'),'reviewer'=>$this->text('reviewer'),'summary'=>$this->text('summary',500),'image_alt'=>$this->text('image_alt',500),'meta_description'=>$this->text('meta_description',500),'related_product_id'=>$this->integer('related_product_id')?:null,'updated_at'=>date('Y-m-d H:i:s')];
                    if(!in_array($data['kind'],['knowledge','news']))throw new \InvalidArgumentException('Choose knowledge or news.');
                    if($data['kind']==='knowledge'&&!array_key_exists($data['topic'],topics()))throw new \InvalidArgumentException('Choose a knowledge topic.');
                    if(!str_starts_with($row['seed_key']??'','demo.')&&$data['kind']==='knowledge'&&isset($_POST['published'])&&$data['reviewer']==='')throw new \InvalidArgumentException('A qualified technical reviewer is required before publishing a knowledge article.');
                    if($data['related_product_id']&&!DB::fetch('SELECT id FROM products WHERE id=?',[$data['related_product_id']]))throw new \InvalidArgumentException('Related product ID does not exist.');
                } elseif($type==='manufacturing_updates') {
                    $data+=['stage'=>$this->text('stage',30),'image_alt'=>$this->text('image_alt',500),'verified'=>isset($_POST['verified'])?1:0,'updated_at'=>date('Y-m-d H:i:s')];
                    if(!in_array($data['stage'],['planned','installation','commissioning','production']))throw new \InvalidArgumentException('Choose a valid project stage.');
                } elseif($type==='dealers') {
                    foreach(['region','city','township','address','phone','map_url'] as $field)$data[$field]=$this->text($field,$field==='address'?3000:($field==='map_url'?500:($field==='phone'?100:190)));
                    if($data['region']===''||$data['city']===''||$data['township']===''||$data['address']==='')throw new \InvalidArgumentException('Region, city, township and address are required.');
                    if(!preg_match('/^[+0-9() .\-]*$/',$data['phone']))throw new \InvalidArgumentException('Enter a valid phone number.');
                    $host=strtolower(parse_url($data['map_url'],PHP_URL_HOST)??'');
                    if($data['map_url']!==''&&(!filter_var($data['map_url'],FILTER_VALIDATE_URL)||parse_url($data['map_url'],PHP_URL_SCHEME)!=='https'||!in_array($host,['www.google.com','google.com','maps.google.com','maps.app.goo.gl','goo.gl'])))throw new \InvalidArgumentException('Use a Google Maps HTTPS link.');
                    $data['verified']=isset($_POST['verified'])?1:0;
                } else {
                    $data+=['document_type'=>$this->text('document_type',40),'verified'=>isset($_POST['verified'])?1:0];
                    if(!in_array($data['document_type'],['catalogue','company_profile','certificate','technical']))throw new \InvalidArgumentException('Choose a valid document type.');
                }
                if(!str_starts_with($row['seed_key']??'','demo.')&&in_array($type,['manufacturing_updates','dealers','documents'])&&isset($_POST['published'])&&!$data['verified'])throw new \InvalidArgumentException('Confirm company verification before publishing this record.');
                if(in_array($type,['articles','manufacturing_updates'])&&trim(html_entity_decode(strip_tags($data['description']),ENT_QUOTES,'UTF-8'))==='')throw new \InvalidArgumentException('Please enter the content for this record.');
            } elseif($type==='contacts') {
                $data+=['email'=>$this->text('email'),'phone'=>$this->text('phone',100),'address'=>$this->text('address',3000),'hours'=>$this->text('hours',255)];
                if($data['email']!==''&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Please enter a valid email address.');
                if(!preg_match('/^[+0-9() .\-]*$/',$data['phone']))throw new \InvalidArgumentException('Please enter a valid phone number.');
            } else {
                $data['description']=$type==='company_sections'?rich_text($this->text('description',100000)):$this->text('description',10000);
                if($type==='slides') {
                    $data+=['button_text'=>$this->text('button_text',100),'button_url'=>$this->text('button_url',255)];
                    if(($data['button_text']!==''||$data['button_url']!=='')&&!safe_link($data['button_url']))throw new \InvalidArgumentException('Add a local path or full http/https URL for the banner button.');
                }
            }
            if($type!=='products') { $order=$this->text('sort_order',10);if(filter_var($order,FILTER_VALIDATE_INT)===false)throw new \InvalidArgumentException('Sort order must be a whole number.');$data['sort_order']=(int)$order; }
            if(!in_array($type,['products','categories']))$data['published']=isset($_POST['published'])?1:0;
            foreach(translation_fields($type) as $field) $data[$field.'_my']=in_array($field,['benefits','applications'])||($field==='description'&&in_array($type,['company_sections','articles','manufacturing_updates']))?rich_text($this->text($field.'_my',100000)):$this->text($field.'_my',10000);
            $fields=$type==='products'?['image'=>'image','data_sheet'=>'pdf','safety_sheet'=>'pdf']:($type==='documents'?['file'=>'pdf']:(in_array($type,['slides','company_sections','articles','manufacturing_updates'])?['image'=>'image']:[]));
            foreach($fields as $field=>$kind) {
                $new=$this->upload($field,$kind,$newFiles);
                if($new||isset($_POST['remove_'.$field])) { $data[$field]=$new; $removed[]=$row[$field]??null; }
            }
            if($type==='documents'&&$data['published']&&!(array_key_exists('file',$data)?$data['file']:($row['file']??null)))throw new \InvalidArgumentException('Upload an approved PDF before publishing.');
            $savedId=DB::transaction(function()use($type,$id,$data,$key){
                if($id){DB::update($type,$id,$data);$savedId=$id;}else $savedId=DB::insert($type,$data);
                $this->activityLog($id?'Updated':'Created',$type,$savedId,$data[$key]);return $savedId;
            });
            $this->cleanFiles($removed);
        } catch(\Throwable $e) {
            $this->cleanFiles($newFiles);
            $message=$e instanceof \InvalidArgumentException?$e->getMessage():($e instanceof \PDOException&&$e->getCode()==='23000'?'This category name already exists.':'Unable to save. Please try again.');
            error_log($e->getMessage());$this->fail(['form'=>$message.' If you selected files, select them again.'],$path);
        }
        redirect(input_text('_action',20,true)==='stay'?'admin/'.$type.'/'.$savedId.'/edit?'.http_build_query(['return_to'=>$returnTo]):$returnTo,'Changes saved.');
    }
    public function delete(string $type,string $id): void {
        $this->type($type);$row=DB::fetch("SELECT * FROM $type WHERE id=?",[(int)$id]);if(!$row)not_found();
        if($type==='categories'&&DB::fetchValue('SELECT COUNT(*) FROM products WHERE category_id=?',[(int)$id]))redirect($this->returnPath($type),'Move or delete the products in this category first.','warning');
        DB::transaction(function()use($type,$id,$row){if($type==='products')DB::statement('UPDATE articles SET related_product_id=NULL WHERE related_product_id=?',[(int)$id]);DB::statement("DELETE FROM $type WHERE id=?",[(int)$id]);$this->activityLog('Deleted',$type,(int)$id,$row['name']??$row['title']);});
        $this->cleanFiles(array_intersect_key($row,array_flip(['image','data_sheet','safety_sheet','file'])));redirect($this->returnPath($type),'Record deleted.');
    }
    public function settings(): void {
        $settingsTab=input_text('tab',20);
        if(!in_array($settingsTab,['brand','account','qr']))$settingsTab='brand';
        $context=$this->settingsContext();
        $row=$settingsTab==='account'?DB::fetch('SELECT email FROM admins WHERE id=?',[$_SESSION['admin_id']]):settings();
        $settingsAction=($settingsTab==='account'?'admin/account':'admin/settings').'?'.http_build_query(['tab'=>$settingsTab]+$context['settingsQuery']);
        $this->render('settings-hub',['title'=>'Settings','settingsTab'=>$settingsTab,'settingsAction'=>$settingsAction,'row'=>$row]+$context);
    }
    public function saveSettings(): void {
        $newFiles=[];$removed=[];
        try {
            $data=['company_name'=>$this->text('company_name'),'tagline'=>$this->text('tagline',255),'intro'=>$this->text('intro',10000),'theme_color'=>$this->text('theme_color',7),'carousel_autoplay'=>isset($_POST['carousel_autoplay'])?1:0];
            $data+=['intro_my'=>$this->text('intro_my',10000),'meta_description'=>$this->text('meta_description',500)];
            if($data['company_name']===''||!preg_match('/^#[0-9a-fA-F]{6}$/',$data['theme_color']))throw new \InvalidArgumentException('Company name and a valid hex color are required.');
            foreach(['logo','favicon'] as $field) { $new=$this->upload($field,'image',$newFiles);if($new||isset($_POST['remove_'.$field])){$data[$field]=$new;$removed[]=settings()[$field];} }
            DB::transaction(function()use($data){DB::update('settings',1,$data);$this->activityLog('Updated','settings',1,'Brand & website settings');});$this->cleanFiles($removed);
        }catch(\Throwable $e){$this->cleanFiles($newFiles);$this->fail(['form'=>$e instanceof \InvalidArgumentException?$e->getMessage():'Unable to save settings.'],$this->settingsPath('brand'));}
        redirect($this->settingsPath('brand'),'Website branding updated.');
    }
    public function account(): void { redirect($this->settingsPath('account')); }
    private function inquiryQuery(): array {
        $filters=['q'=>input_text('q'),'status'=>input_text('status',20),'kind'=>input_text('kind',30),'from'=>input_text('from',10),'to'=>input_text('to',10)];$where='1=1';$bind=[];$errors=[];
        foreach(['status'=>['new','in_progress','resolved'],'kind'=>['product','complaint','general','distributor']] as $key=>$allowed)if(!in_array($filters[$key],$allowed))$filters[$key]='';
        foreach(['status','kind'] as $key)if($filters[$key]!==''){$where.=" AND $key=?";$bind[]=$filters[$key];}
        if($filters['q']!==''){$where.=" AND CONCAT_WS(' ',reference,full_name,email,company_name,country) LIKE ? ESCAPE '!'";$bind[]=$this->like($filters['q']);}
        foreach(['from','to'] as $key)if($filters[$key]!==''){$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$filters[$key]);if(!$date||$date->format('Y-m-d')!==$filters[$key])$errors[]='Enter a valid '.$key.' date.';else{$where.=$key==='from'?' AND created_at>=?':' AND created_at<?';$bind[]=$key==='from'?$date->format('Y-m-d'):$date->modify('+1 day')->format('Y-m-d');}}
        if($filters['from']!==''&&$filters['to']!==''&&$filters['from']>$filters['to'])$errors[]='The end date must be on or after the start date.';
        if($errors)$where.=' AND 1=0';return [$filters,$where,$bind,$errors];
    }
    public function inquiries(): void {
        [$filters,$where,$bind,$errors]=$this->inquiryQuery();
        $total=(int)DB::fetchValue("SELECT COUNT(*) FROM inquiries WHERE $where",$bind);$pages=max(1,(int)ceil($total/20));$page=max(1,min($pages,(int)($_GET['page']??1)));$offset=($page-1)*20;
        $this->render('inquiries',['title'=>'Customer & business inquiries','rows'=>DB::fetchAll("SELECT * FROM inquiries WHERE $where ORDER BY id DESC LIMIT 20 OFFSET $offset",$bind),'filters'=>$filters,'filterErrors'=>$errors,'status'=>$filters['status'],'q'=>$filters['q'],'total'=>$total,'page'=>$page,'pages'=>$pages]);
    }
    public function exportInquiries(): void {
        [$filters,$where,$bind,$errors]=$this->inquiryQuery();if($errors){http_response_code(422);header('Content-Type: text/plain; charset=UTF-8');echo implode(' ',$errors);return;}
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="superking-inquiries-'.date('Y-m-d').'.csv"');
        $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");$fields=['reference','kind','status','full_name','company_name','country','email','phone','business_type','categories','volume','network','message','batch_number','internal_notes','created_at','updated_at'];
        fputcsv($out,array_map(fn($field)=>ucwords(str_replace('_',' ',$field)),$fields),',','"','');$offset=0;
        do{$rows=DB::fetchAll("SELECT * FROM inquiries WHERE $where ORDER BY id DESC LIMIT 500 OFFSET $offset",$bind);foreach($rows as $row){$values=[];foreach($fields as $field){$value=(string)($row[$field]??'');$values[]=preg_match('/^[\s\x{FEFF}]*[=+\-@]/u',$value)?"'".$value:$value;}fputcsv($out,$values,',','"','');}$offset+=500;}while(count($rows)===500);fclose($out);
    }
    public function inquiry(string $id): void { $row=DB::fetch('SELECT i.*,a.email handler_email FROM inquiries i LEFT JOIN admins a ON a.id=i.handled_by WHERE i.id=?',[(int)$id]);if(!$row)not_found();$this->render('inquiry',['title'=>'Inquiry '.$row['reference'],'row'=>$row,'returnTo'=>$this->returnPath('inquiries')]); }
    public function updateInquiry(string $id): void {
        $row=DB::fetch('SELECT * FROM inquiries WHERE id=?',[(int)$id]);if(!$row)not_found();
        try{$status=$this->text('status',20);$notes=array_key_exists('internal_notes',$_POST)?$this->text('internal_notes',10000):($row['internal_notes']??'');if(!in_array($status,['new','in_progress','resolved']))throw new \InvalidArgumentException('Choose a valid status.');}catch(\InvalidArgumentException $e){$this->fail(['form'=>$e->getMessage()],'admin/inquiries/'.$id.'?'.http_build_query(['return_to'=>$this->returnPath('inquiries')]));}
        DB::transaction(function()use($id,$status,$notes){DB::update('inquiries',(int)$id,['status'=>$status,'internal_notes'=>$notes,'updated_at'=>date('Y-m-d H:i:s'),'handled_by'=>$_SESSION['admin_id']]);$this->activityLog('Updated','inquiries',(int)$id,'Inquiry #'.$id.' — '.str_replace('_',' ',$status));});redirect('admin/inquiries/'.$id.'?'.http_build_query(['return_to'=>$this->returnPath('inquiries')]),'Inquiry status and notes updated.');
    }
    public function deleteInquiry(string $id): void { $row=DB::fetch('SELECT reference FROM inquiries WHERE id=?',[(int)$id]);if(!$row)not_found();DB::transaction(function()use($id,$row){DB::statement('DELETE FROM inquiries WHERE id=?',[(int)$id]);$this->activityLog('Deleted','inquiries',(int)$id,$row['reference']);});redirect($this->returnPath('inquiries'),'Inquiry permanently deleted.'); }
    public function activity(): void {
        $q=input_text('q');$type=input_text('type',50);$where='1=1';$bind=[];
        if($q!==''){$where.=" AND CONCAT_WS(' ',actor,record_label,action) LIKE ? ESCAPE '!'";$bind[]=$this->like($q);}
        if($type!==''){$where.=' AND content_type=?';$bind[]=$type;}
        $total=(int)DB::fetchValue("SELECT COUNT(*) FROM admin_activity WHERE $where",$bind);$pages=max(1,(int)ceil($total/20));$page=max(1,min($pages,(int)($_GET['page']??1)));$offset=($page-1)*20;
        $this->render('activity',['title'=>'Activity history','rows'=>DB::fetchAll("SELECT * FROM admin_activity WHERE $where ORDER BY id DESC LIMIT 20 OFFSET $offset",$bind),'q'=>$q,'type'=>$type,'total'=>$total,'pages'=>$pages,'page'=>$page]);
    }
    public function preview(string $type,string $id): void {
        if(!in_array($type,['products','articles']))not_found();$row=DB::fetch("SELECT * FROM $type WHERE id=?",[(int)$id]);if(!$row)not_found();
        $data=['adminPreview'=>true,'previewEditPath'=>'admin/'.$type.'/'.$row['id'].'/edit','title'=>localized($row,$type==='products'?'name':'title')];
        if($type==='products'){$category=DB::fetch('SELECT name,name_my FROM categories WHERE id=?',[$row['category_id']]);$row['category_name']=$category['name'];$row['category_name_my']=$category['name_my'];view('site/product',$data+['product'=>$row]);}
        else{$related=$row['related_product_id']?DB::fetch('SELECT * FROM products WHERE id=? AND published=1',[$row['related_product_id']]):null;view('site/article',$data+['article'=>$row,'related'=>$related]);}
    }
    public function saveAccount(): void {
        $admin=DB::fetch('SELECT * FROM admins WHERE id=?',[$_SESSION['admin_id']]);
        if(!is_string($_POST['current_password']??null)||!password_verify($_POST['current_password'],$admin['password']))$this->fail(['form'=>'Current password is incorrect.'],$this->settingsPath('account'));
        $email=input_text('email',191,true);$password=$_POST['new_password']??'';$confirm=$_POST['confirm_password']??'';
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190||!is_string($password)||!is_string($confirm)||($password!==''&&(strlen($password)<12||strlen($password)>4096))||$password!==$confirm)$this->fail(['form'=>'Enter a valid email. Optional new passwords must match and contain at least 12 characters.'],$this->settingsPath('account'));
        $data=['email'=>$email];if($password!=='')$data['password']=password_hash($password,PASSWORD_DEFAULT);
        try { DB::transaction(function()use($data){DB::update('admins',(int)$_SESSION['admin_id'],$data);$this->activityLog('Updated','account',(int)$_SESSION['admin_id'],'Administrator sign-in details');}); }catch(\PDOException $e){$this->fail(['form'=>'Unable to update the account. Check whether that email is already in use.'],$this->settingsPath('account'));}
        session_regenerate_id(true);redirect($this->settingsPath('account'),'Account updated.');
    }
}
