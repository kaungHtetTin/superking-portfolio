<?php
namespace App\Controllers;
use Mini\Database as DB;
final class AdminController {
    public const TYPES = ['products'=>'Products','categories'=>'Categories','slides'=>'Carousel slides','company_sections'=>'Company information','contacts'=>'Company contacts'];
    private function type(string $type): void { if (!isset(self::TYPES[$type])) not_found(); }
    private function render(string $view,array $data=[]): void { view('admin/'.$view,$data,'layouts/admin'); }
    private function fail(array $errors, string $path): never {
        $_SESSION['errors']=$errors; $_SESSION['old']=array_filter($_POST,fn($v,$k)=>is_scalar($v)&&!str_contains($k,'password')&&$k!=='_token',ARRAY_FILTER_USE_BOTH); redirect($path);
    }
    public function login(): void { if (isset($_SESSION['admin_id'])) redirect('admin'); view('admin/login',['title'=>'Admin sign in'],null); }
    public function authenticate(): void {
        $email=trim((string)($_POST['email']??'')); $password=(string)($_POST['password']??'');
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
        $this->render('dashboard',['title'=>'Overview','counts'=>$counts,'recent'=>DB::fetchAll('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id ORDER BY updated_at DESC LIMIT 6')]);
    }
    public function listing(string $type): void {
        $this->type($type); $q=mb_substr(trim((string)($_GET['q']??'')),0,190); $column=$type==='products'||$type==='categories'?'name':'title';
        $where="$column LIKE ?"; $bind=['%'.$q.'%']; $category=(int)($_GET['category']??0);
        if($type==='products'&&$category){$where.=' AND category_id=?';$bind[]=$category;}
        $total=(int)DB::fetchValue("SELECT COUNT(*) FROM $type WHERE $where",$bind); $pages=max(1,(int)ceil($total/20)); $page=max(1,min($pages,(int)($_GET['page']??1)));$offset=($page-1)*20;
        $this->render('list',['title'=>self::TYPES[$type],'type'=>$type,'q'=>$q,'category'=>$category,'categories'=>DB::fetchAll('SELECT * FROM categories ORDER BY sort_order,name'),'rows'=>DB::fetchAll("SELECT * FROM $type WHERE $where ORDER BY id DESC LIMIT 20 OFFSET $offset",$bind),'total'=>$total,'pages'=>$pages,'page'=>$page]);
    }
    public function form(string $type,?string $id=null): void {
        $this->type($type); $row=$id?DB::fetch("SELECT * FROM $type WHERE id=?",[(int)$id]):[];if($id&&!$row)not_found();
        $this->render('form',['title'=>($id?'Edit ':'Add ').self::TYPES[$type],'type'=>$type,'row'=>$row,'categories'=>DB::fetchAll('SELECT * FROM categories ORDER BY sort_order,name')]);
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
    private function cleanFiles(array $paths): void { foreach($paths as $path)if(is_string($path)&&preg_match('#^uploads/[a-f0-9]{32}\.(jpg|png|webp|pdf)$#',$path)&&is_file(BASE_PATH.'/public/'.$path))unlink(BASE_PATH.'/public/'.$path); }
    private function text(string $key,int $max=190): string { $v=$_POST[$key]??'';if(!is_string($v))throw new \InvalidArgumentException('Invalid field value.');if(mb_strlen($v)>$max)throw new \InvalidArgumentException(ucwords(str_replace('_',' ',$key))." must be $max characters or fewer.");return trim($v); }
    public function save(string $type): void {
        $this->type($type);$id=(int)($_POST['id']??0);$row=$id?DB::fetch("SELECT * FROM $type WHERE id=?",[$id]):[];if($id&&!$row)not_found();
        $path='admin/'.$type.($id?"/$id/edit":'/create');$newFiles=[];$removed=[];
        try {
            $key=in_array($type,['products','categories'])?'name':'title';$data=[$key=>$this->text($key)];
            if($data[$key]==='')throw new \InvalidArgumentException('A name or title is required.');
            if($type==='products') {
                $data+=['category_id'=>(int)($_POST['category_id']??0),'summary'=>$this->text('summary',500),'benefits'=>rich_text($this->text('benefits',100000)),'applications'=>rich_text($this->text('applications',100000)),'published'=>isset($_POST['published'])?1:0,'featured'=>isset($_POST['featured'])?1:0,'updated_at'=>date('Y-m-d H:i:s')];
                if(!DB::fetch('SELECT id FROM categories WHERE id=?',[$data['category_id']]))throw new \InvalidArgumentException('Please select an existing category.');
            } elseif($type==='contacts') {
                $data+=['email'=>$this->text('email'),'phone'=>$this->text('phone',100),'address'=>$this->text('address',3000),'hours'=>$this->text('hours',255)];
                if($data['email']!==''&&!filter_var($data['email'],FILTER_VALIDATE_EMAIL))throw new \InvalidArgumentException('Please enter a valid email address.');
                if(!preg_match('/^[+0-9() .\-]*$/',$data['phone']))throw new \InvalidArgumentException('Please enter a valid phone number.');
            } else {
                $data['description']=$type==='company_sections'?rich_text($this->text('description',100000)):$this->text('description',10000);
                if($type==='slides') {
                    $data+=['button_text'=>$this->text('button_text',100),'button_url'=>$this->text('button_url',255)];
                    if(!safe_link($data['button_url']))throw new \InvalidArgumentException('Button URL must be a local path such as /products or a full http/https URL.');
                }
            }
            if($type!=='products') { $order=$this->text('sort_order',10);if(filter_var($order,FILTER_VALIDATE_INT)===false)throw new \InvalidArgumentException('Sort order must be a whole number.');$data['sort_order']=(int)$order; }
            if(!in_array($type,['products','categories']))$data['published']=isset($_POST['published'])?1:0;
            $fields=$type==='products'?['image'=>'image','data_sheet'=>'pdf','safety_sheet'=>'pdf']:(in_array($type,['slides','company_sections'])?['image'=>'image']:[]);
            foreach($fields as $field=>$kind) {
                $new=$this->upload($field,$kind,$newFiles);
                if($new||isset($_POST['remove_'.$field])) { $data[$field]=$new; $removed[]=$row[$field]??null; }
            }
            if($id)DB::update($type,$id,$data);else DB::insert($type,$data);
            $this->cleanFiles($removed);
        } catch(\Throwable $e) {
            $this->cleanFiles($newFiles);
            $message=$e instanceof \InvalidArgumentException?$e->getMessage():($e instanceof \PDOException&&$e->getCode()==='23000'?'This category name already exists.':'Unable to save. Please try again.');
            error_log($e->getMessage());$this->fail(['form'=>$message.' If you selected files, select them again.'],$path);
        }
        redirect('admin/'.$type,'Changes saved.');
    }
    public function delete(string $type,string $id): void {
        $this->type($type);$row=DB::fetch("SELECT * FROM $type WHERE id=?",[(int)$id]);if(!$row)not_found();
        if($type==='categories'&&DB::fetchValue('SELECT COUNT(*) FROM products WHERE category_id=?',[(int)$id]))redirect('admin/categories','Move or delete the products in this category first.','warning');
        DB::statement("DELETE FROM $type WHERE id=?",[(int)$id]);$this->cleanFiles(array_intersect_key($row,array_flip(['image','data_sheet','safety_sheet'])));redirect('admin/'.$type,'Record deleted.');
    }
    public function settings(): void { $this->render('settings',['title'=>'Brand & website settings','row'=>settings()]); }
    public function saveSettings(): void {
        $newFiles=[];$removed=[];
        try {
            $data=['company_name'=>$this->text('company_name'),'tagline'=>$this->text('tagline',255),'intro'=>$this->text('intro',10000),'theme_color'=>$this->text('theme_color',7),'carousel_autoplay'=>isset($_POST['carousel_autoplay'])?1:0];
            if($data['company_name']===''||!preg_match('/^#[0-9a-fA-F]{6}$/',$data['theme_color']))throw new \InvalidArgumentException('Company name and a valid hex color are required.');
            foreach(['logo','favicon'] as $field) { $new=$this->upload($field,'image',$newFiles);if($new||isset($_POST['remove_'.$field])){$data[$field]=$new;$removed[]=settings()[$field];} }
            DB::update('settings',1,$data);$this->cleanFiles($removed);
        }catch(\Throwable $e){$this->cleanFiles($newFiles);$this->fail(['form'=>$e instanceof \InvalidArgumentException?$e->getMessage():'Unable to save settings.'],'admin/settings');}
        redirect('admin/settings','Website branding updated.');
    }
    public function account(): void { $this->render('account',['title'=>'Your account','row'=>DB::fetch('SELECT email FROM admins WHERE id=?',[$_SESSION['admin_id']])]); }
    public function saveAccount(): void {
        $admin=DB::fetch('SELECT * FROM admins WHERE id=?',[$_SESSION['admin_id']]);
        if(!password_verify((string)($_POST['current_password']??''),$admin['password']))$this->fail(['form'=>'Current password is incorrect.'],'admin/account');
        $email=trim((string)($_POST['email']??''));$password=(string)($_POST['new_password']??'');
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190||strlen($password)<12||$password!==($_POST['confirm_password']??''))$this->fail(['form'=>'Enter a valid email and matching new passwords of at least 12 characters.'],'admin/account');
        try { DB::update('admins',(int)$_SESSION['admin_id'],['email'=>$email,'password'=>password_hash($password,PASSWORD_DEFAULT)]); }catch(\PDOException $e){$this->fail(['form'=>'That email is already in use.'],'admin/account');}
        session_regenerate_id(true);redirect('admin/account','Account updated.');
    }
}
