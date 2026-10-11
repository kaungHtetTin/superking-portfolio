<?php
namespace App\Controllers;
use Mini\Database as DB;
final class SiteController {
    public function home(): void {
        view('site/home', ['title'=>settings()['company_name'], 'slides'=>DB::fetchAll('SELECT * FROM slides WHERE published=1 ORDER BY sort_order,id'), 'categories'=>DB::fetchAll('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id=c.id AND p.published=1) AS product_count FROM categories c ORDER BY sort_order,id'), 'products'=>DB::fetchAll('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.published=1 ORDER BY featured DESC,id DESC LIMIT 4'), 'sections'=>DB::fetchAll('SELECT * FROM company_sections WHERE published=1 ORDER BY sort_order,id LIMIT 2')]);
    }
    public function products(): void {
        $query = $_GET['q'] ?? '';
        $q = mb_substr(trim(is_string($query) ? $query : ''),0,190);
        $category = filter_var($_GET['category'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
        $where = 'p.published=1'; $bind = [];
        if ($q !== '') {
            // Match every word across the searchable fields; treat LIKE wildcards literally.
            foreach (preg_split('/\s+/u', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
                $where .= " AND (p.name LIKE ? ESCAPE '!' OR p.summary LIKE ? ESCAPE '!' OR c.name LIKE ? ESCAPE '!' OR p.applications LIKE ? ESCAPE '!' OR p.benefits LIKE ? ESCAPE '!')";
                $pattern = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
                array_push($bind, ...array_fill(0, 5, $pattern));
            }
        }
        if ($category) { $where .= ' AND p.category_id=?'; $bind[] = $category; }
        $total = (int) DB::fetchValue("SELECT COUNT(*) FROM products p JOIN categories c ON c.id=p.category_id WHERE $where",$bind);
        $pages = max(1,(int)ceil($total/12)); $page = max(1,min($pages,(int)($_GET['page']??1))); $offset=($page-1)*12;
        view('site/products', ['title'=>'Product catalogue','q'=>$q,'category'=>$category,'page'=>$page,'pages'=>$pages,'total'=>$total,'categories'=>DB::fetchAll('SELECT * FROM categories ORDER BY sort_order,id'),'products'=>DB::fetchAll("SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE $where ORDER BY p.featured DESC,p.name LIMIT 12 OFFSET $offset",$bind)]);
    }
    public function product(string $id): void {
        $product = DB::fetch('SELECT p.*,c.name category_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.published=1',[(int)$id]);
        if (!$product) not_found(); view('site/product',['title'=>$product['name'],'product'=>$product]);
    }
    public function company(): void { view('site/company',['title'=>'Our company','sections'=>DB::fetchAll('SELECT * FROM company_sections WHERE published=1 ORDER BY sort_order,id')]); }
    public function contact(): void { view('site/contact',['title'=>'Contact us','contacts'=>DB::fetchAll('SELECT * FROM contacts WHERE published=1 ORDER BY sort_order,id')]); }
}
