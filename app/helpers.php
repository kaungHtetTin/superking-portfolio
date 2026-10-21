<?php
use Mini\Database as DB;
function settings(): array {
    static $settings;
    return $settings ??= DB::fetch('SELECT * FROM settings WHERE id = 1') ?? ['company_name'=>'Super King','tagline'=>'Oils & lubricants','theme_color'=>'#b52025','logo'=>null,'favicon'=>null,'intro'=>'','carousel_autoplay'=>0];
}
function media(?string $path): string { return $path ? url($path) : asset('images/product.svg'); }
function brand_foreground(string $hex): string {
    $rgb = array_map(fn($v) => hexdec($v)/255, str_split(substr($hex,1),2));
    $rgb = array_map(fn($v) => $v <= .04045 ? $v/12.92 : (($v+.055)/1.055)**2.4,$rgb);
    return (.2126*$rgb[0]+.7152*$rgb[1]+.0722*$rgb[2]) > .179 ? '#172033' : '#ffffff';
}
function not_found(): never { http_response_code(404); view('errors/404', [], 'layouts/app'); exit; }
function rich_text(string $html): string {
    $doc = new DOMDocument(); $previous = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="rich-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    $render = function ($node) use (&$render): string {
        if ($node instanceof DOMText) return e($node->nodeValue);
        if (!($node instanceof DOMElement)) return '';
        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script','style','iframe','object','svg','math','form','input','textarea'])) return '';
        $children = ''; foreach ($node->childNodes as $child) $children .= $render($child);
        return in_array($tag, ['p','div','br','strong','b','em','i','u','ul','ol','li','h3','h4','blockquote']) ? '<'.$tag.'>'.$children.($tag === 'br' ? '' : '</'.$tag.'>') : $children;
    };
    $root = $doc->getElementById('rich-root'); if (!$root) return '';
    $result = ''; foreach ($root->childNodes as $node) $result .= $render($node); return $result;
}
function field_value(array $row, string $name, mixed $default = ''): mixed {
    if (isset($_SESSION['old']) && in_array($name,['published','featured','verified','carousel_autoplay'])) return $_SESSION['old'][$name] ?? 0;
    return old($name, $row[$name] ?? $default);
}
function safe_link(string $v): bool { return (bool)preg_match('#^/(?!/)[^\s]*$#', $v) || (filter_var($v, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($v, PHP_URL_SCHEME) ?? ''), ['http','https'])); }

function language(): string { return ($_GET['lang'] ?? '') === 'my' ? 'my' : 'en'; }
function t(string $en, string $my): string { return language()==='my' ? $my : $en; }
function localized(array $row, string $key): string { return (string)((language()==='my' && !empty($row[$key.'_my'])) ? $row[$key.'_my'] : ($row[$key]??'')); }
function site_url(string $path=''): string { return url($path.(str_contains($path,'?')?'&':'?').'lang='.language()); }
function language_url(string $lang): string {
    $params=$_GET; $params['lang']=$lang;
    $path=parse_url($_SERVER['REQUEST_URI']??'/',PHP_URL_PATH)?:'/';
    return $path.'?'.http_build_query($params);
}
function input_text(string $key, int $max=190, bool $post=false): string {
    $value=($post?$_POST:$_GET)[$key]??'';
    return is_string($value)?mb_substr(trim($value),0,$max):'';
}
function published_contacts(): array { return DB::fetchAll('SELECT * FROM contacts WHERE published=1 ORDER BY sort_order,id'); }
function approved_documents(): array { return DB::fetchAll('SELECT * FROM documents WHERE published=1 AND (verified=1 OR LEFT(seed_key,5)=0x64656d6f2e) AND file IS NOT NULL ORDER BY sort_order,id'); }
function topics(): array { return [
    'Engine Oil Fundamentals'=>t('Engine Oil Fundamentals','အင်ဂျင်ဝိုင် အခြေခံဗဟုသုတ'),
    'Motorcycle Lubricants'=>t('Motorcycle Lubricants','မော်တော်ဆိုင်ကယ် ချောဆီများ'),
    'Passenger Car Lubricants'=>t('Passenger Car Lubricants','လူစီးကား ချောဆီများ'),
    'Heavy-Duty & Commercial Vehicles'=>t('Heavy-Duty & Commercial Vehicles','ကုန်တင်ယာဉ်နှင့် လုပ်ငန်းသုံးယာဉ်များ'),
    'Other Lubricants'=>t('Other Lubricants','အခြား ချောဆီများ'),
    'Myanmar Operating Conditions'=>t('Myanmar Operating Conditions','မြန်မာနိုင်ငံရှိ မောင်းနှင်မှုအခြေအနေများ'),
    'Frequently Asked Questions'=>t('Frequently Asked Questions','မေးလေ့ရှိသော မေးခွန်းများ')]; }
function translation_fields(string $type): array {
    return match($type) {
        'products'=>['name','summary','benefits','applications'],
        'categories'=>['name','description'], 'slides'=>['title','description','button_text'],
        'contacts'=>['title','address','hours'], 'articles'=>['title','summary','description'],
        default=>['title','description']
    };
}
function manufacturing_stage(): string {
    static $stage;
    return $stage??=DB::fetchValue('SELECT stage FROM manufacturing_updates WHERE published=1 AND verified=1 ORDER BY updated_at DESC,id DESC LIMIT 1')?:'planned';
}
function admin_public_path(string $type,array $row): ?string {
    if(empty($row['published']))return null;
    if(!str_starts_with($row['seed_key']??'','demo.')&&(isset($row['verified'])&&!$row['verified']||$type==='articles'&&$row['kind']==='knowledge'&&empty($row['reviewer'])))return null;
    return match($type){'products'=>'products/'.$row['id'],'articles'=>'articles/'.$row['id'],'slides'=>'','company_sections'=>'company','contacts'=>'contact','manufacturing_updates'=>'manufacturing','dealers'=>'dealers?q='.rawurlencode($row['title']),'documents'=>'distributor',default=>null};
}

function published_demo_content(): bool {
    static $visible;
    if($visible!==null)return $visible;
    foreach(\App\Content\DemoContent::TABLES as $table)if(DB::fetchValue("SELECT id FROM $table WHERE published=1 AND LEFT(seed_key,5)=0x64656d6f2e LIMIT 1"))return $visible=true;
    return $visible=false;
}

function application_photo_caption(?string $path): string {
    if(!$path||!str_starts_with($path,'assets/images/applications/'))return '';
    $credits=[
        'motorcycle'=>['Gera Cejas',27704018], 'passenger'=>['Anna Shvets',4315574],
        'heavy-duty'=>['Ahmet Polat',5410923], 'industrial'=>['Roshan Ravi',32900156],
        'transmission'=>['Connor Lucock',28752154]
    ];
    [$name,$id]=$credits[pathinfo($path,PATHINFO_FILENAME)]??['Pexels',0];
    return '<span class="photo-caption">'.e(t('Illustrative application photo','အသုံးပြုမှု နမူနာဓာတ်ပုံ')).' &middot; '.e($name).' / Pexels</span>';
}
