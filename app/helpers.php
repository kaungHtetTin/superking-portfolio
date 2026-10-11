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
    if (isset($_SESSION['old']) && in_array($name,['published','featured','carousel_autoplay'])) return $_SESSION['old'][$name] ?? 0;
    return old($name, $row[$name] ?? $default);
}
function safe_link(string $v): bool { return (bool)preg_match('#^/(?!/)[^\s]*$#', $v) || (filter_var($v, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($v, PHP_URL_SCHEME) ?? ''), ['http','https'])); }
