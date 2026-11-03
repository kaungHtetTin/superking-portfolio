"""Spec regression suite. Creates, serves and removes its own disposable MySQL DB.

Run: python tests/specification.py
Requires requests, opencv-python and (for visual checks) playwright plus system Microsoft Edge.
Company database records and administrator passwords are never changed.
"""
import base64
import csv
import io
import os
from pathlib import Path
import re
import socket
import subprocess
import time
import xml.etree.ElementTree as ET

import requests

ROOT = Path(__file__).resolve().parents[1]
database = 'superking_qa_' + str(time.time_ns())
assert re.fullmatch(r'superking_qa_\d+', database)
env = dict(os.environ, DB_DATABASE=database, APP_URL='', APP_DEBUG='true', SEED_PROFILE='minimal', SEED_ADMIN_PASSWORD='Specification-admin-password-2026')
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0))
    port = sock.getsockname()[1]
BASE = f'http://127.0.0.1:{port}'
server = None
log = None


def php(*args):
    return subprocess.run(['php', *args], cwd=ROOT, env=env, capture_output=True, text=True, encoding='utf-8', check=True).stdout


try:
    php('bin/console', 'migrate')
    seed = php('bin/console', 'db:seed')
    password = re.search(r'Initial password: ([^\r\n]+)', seed).group(1)
    assert 'up to date' in php('bin/console', 'migrate')  # migration names survive file variable scopes
    try:
        php('bin/console', 'migrate:rollback')
        raise AssertionError('Forward-only rollback should be refused')
    except subprocess.CalledProcessError as error:
        assert 'forward-only' in error.stderr
    out = ROOT / 'storage/qa'
    out.mkdir(parents=True, exist_ok=True)
    log = (out / 'platform-server.log').open('w', encoding='utf-8')
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', 'public', 'public/router.php'], cwd=ROOT, env=env, stdout=log, stderr=log, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    for _ in range(50):
        try:
            if requests.get(BASE, timeout=1).status_code == 200:
                break
        except requests.RequestException:
            time.sleep(.1)
    else:
        raise AssertionError('Disposable test server did not start')

    admin = requests.Session()
    public = requests.Session()

    def get(path, session=admin):
        response = session.get(BASE + path, timeout=10)
        assert response.status_code == 200, (path, response.status_code, response.text[:500])
        assert not any(error in response.text for error in ['Application error', 'Warning:', 'Fatal error:']), path
        return response

    def token(path, session=admin):
        return re.search(r'name="_token" value="([^"]+)"', get(path, session).text).group(1)

    def post(path, data, source, session=admin, files=None, error=False):
        response = session.post(BASE + path, data=dict(data, _token=token(source, session)), files=files, timeout=10)
        assert response.status_code == 200, (path, response.status_code, response.text[:300])
        assert 'Application error' not in response.text
        assert ('flash error' in response.text) == error, (path, response.text[-1200:])
        return response

    def record_id(kind, title):
        listing = get('/admin/' + kind + '?q=' + requests.utils.quote(title)).text
        return int(re.search('/admin/' + kind + r'/(\d+)/edit', listing).group(1))

    routes = ['/', '/company', '/products', '/manufacturing', '/knowledge', '/news', '/dealers', '/distributor', '/contact', '/privacy']
    for path in routes:
        get(path, public)
        my = get(path + ('&' if '?' in path else '?') + 'lang=my', public).text
        assert '<html lang="my">' in my
        assert 'lang=en' in my and 'hreflang="my"' in my
    assert 'factory.jpg' not in get('/', public).text
    assert 'product.svg' not in get('/products', public).text
    assert public.get(BASE + '/admin', allow_redirects=False).status_code == 302
    assert public.post(BASE + '/inquiry', data={'full_name': 'CSRF'}).status_code == 403
    post('/admin/login', {'email': 'admin@superking.local', 'password': password}, '/admin/login')
    for kind in ['products', 'categories', 'slides', 'company_sections', 'contacts', 'articles', 'manufacturing_updates', 'dealers', 'documents']:
        get('/admin/' + kind)
        get('/admin/' + kind + '/create')
    get('/admin/inquiries')

    category = int(re.search(r'<option value="(\d+)"', get('/admin/products/create').text).group(1))
    product = {'name': 'QA Superking sample', 'name_my': 'စမ်းသပ် ချောဆီ', 'category_id': category,
               'summary': 'QA sample for search', 'benefits': '<p><b>Test text</b></p><script>alert(1)</script>',
               'applications': '<p>QA machinery</p>', 'product_code': 'QA-001', 'viscosity': 'QA-10',
               'specification': 'QA-SPEC', 'vehicle_types': 'QA equipment', 'package_sizes': 'QA-size',
               'meta_description': 'QA product meta', 'published': 1, 'featured': 1}
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aF1sAAAAASUVORK5CYII=')
    post('/admin/products/save', product, '/admin/products/create', files={'image': ('sample.png', png, 'image/png')})
    product_id = record_id('products', product['name'])
    from product_qr import check_http as check_qr_http
    check_qr_http(BASE, admin, public, get, product_id)
    detail = get(f'/products/{product_id}', public).text
    assert 'QA-001' in detail and 'QA-SPEC' in detail and 'QA-size' in detail
    assert 'alert(1)' not in detail and '<b>Test text</b>' in detail
    assert 'စမ်းသပ် ချောဆီ' in get(f'/products/{product_id}?lang=my', public).text
    assert 'QA product meta' in detail
    for query, expected in [('sample QA', 1), ('QA-001', 1), ('QA machinery', 1), ('%', 0), ('_', 0), ("' OR 1=1 --", 0)]:
        html = get('/products?q=' + requests.utils.quote(query), public).text
        assert f'class="results-line"><span>{expected} product' in html, query
    assert '1 product' in get('/products?viscosity=QA-10&specification=QA-SPEC&application=QA%20equipment', public).text
    assert '0 products' in get('/products?viscosity=invalid', public).text
    assert 'Array to string conversion' not in get('/products?q[]=bad&category[]=bad&viscosity[]=bad', public).text

    article = {'title': 'QA knowledge', 'description': '<p>Reviewed article</p>', 'summary': 'QA summary', 'kind': 'knowledge', 'topic': 'Engine Oil Fundamentals', 'sort_order': 0, 'published': 1, 'related_product_id': product_id}
    post('/admin/articles/save', article, '/admin/articles/create', error=True)
    article['reviewer'] = 'QA reviewer'
    post('/admin/articles/save', article, '/admin/articles/create')
    article_id = record_id('articles', 'QA knowledge')
    assert 'QA knowledge' in get('/knowledge?topic=Engine%20Oil%20Fundamentals', public).text
    assert 'QA reviewer' in get(f'/articles/{article_id}', public).text
    assert 'QA knowledge' not in get('/news', public).text
    assert f'/products/{product_id}?lang=en' in get(f'/articles/{article_id}', public).text
    news = dict(article, title='QA news', kind='news', reviewer='', related_product_id='')
    post('/admin/articles/save', news, '/admin/articles/create')
    assert 'QA news' in get('/news', public).text

    update = {'title': 'QA installation plan', 'description': '<p>Planned only</p>', 'stage': 'planned', 'sort_order': 0, 'published': 1}
    post('/admin/manufacturing_updates/save', update, '/admin/manufacturing_updates/create', error=True)
    post('/admin/manufacturing_updates/save', dict(update, verified=1), '/admin/manufacturing_updates/create')
    assert 'QA installation plan' in get('/manufacturing', public).text
    update_id = record_id('manufacturing_updates', 'QA installation plan')
    post('/admin/manufacturing_updates/save', dict(update, id=update_id, stage='production', verified=1), f'/admin/manufacturing_updates/{update_id}/edit')
    progress = get('/manufacturing', public).text
    assert '<h3>Production</h3>' in progress and '<h3>First production</h3>' not in progress

    dealer = {'title': 'QA dealer', 'description': 'QA official dealer', 'region': 'Yangon', 'city': 'Yangon', 'township': 'QA township', 'address': 'QA street', 'phone': '+95 12345', 'map_url': 'javascript:alert(1)', 'sort_order': 0, 'published': 1, 'verified': 1}
    post('/admin/dealers/save', dealer, '/admin/dealers/create', error=True)
    dealer['map_url'] = 'https://www.google.com/maps?q=Yangon'
    post('/admin/dealers/save', dealer, '/admin/dealers/create')
    assert 'QA dealer' in get('/dealers?region=Yangon&q=QA%20township', public).text
    assert 'QA dealer' not in get('/dealers?region=Mandalay', public).text

    document = {'title': 'QA catalogue', 'description': 'QA document', 'document_type': 'catalogue', 'sort_order': 0, 'published': 1, 'verified': 1}
    post('/admin/documents/save', document, '/admin/documents/create', error=True)
    pdf = b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
    post('/admin/documents/save', document, '/admin/documents/create', files={'file': ('qa.pdf', pdf, 'application/pdf')})
    document_id = record_id('documents', 'QA catalogue')
    assert 'QA catalogue' in get('/distributor', public).text
    post('/admin/documents/save', dict(document, id=document_id, remove_file=1), f'/admin/documents/{document_id}/edit', error=True)
    post('/admin/documents/save', dict(document, id=document_id), f'/admin/documents/{document_id}/edit', files={'file': ('fake.pdf', b'<?php echo 1;?>', 'application/pdf')}, error=True)

    inquiry = {'kind': 'distributor', 'full_name': 'QA sender', 'email': 'qa@example.com', 'company_name': 'QA business', 'country': 'Myanmar', 'business_type': 'Importer', 'categories': 'Motorcycle oils', 'phone': '+95 12345', 'volume': 'To discuss', 'network': 'QA network', 'message': '<script>evil()</script>', 'consent': '1'}
    token('/distributor', public)
    time.sleep(2.1)
    post('/inquiry', dict(inquiry, email='invalid'), '/distributor', public, error=True)
    post('/inquiry', dict(inquiry, website='spam'), '/distributor', public, error=True)
    post('/inquiry', dict(inquiry, message='x' * 5001), '/distributor', public, error=True)
    post('/inquiry', dict(inquiry, consent=''), '/distributor', public, error=True)
    received = post('/inquiry', inquiry, '/distributor', public)
    reference = re.search(r'SK-\d{6}-[A-F0-9]{8}', received.text).group(0)
    inbox = get('/admin/inquiries?q=' + reference).text
    inquiry_id = int(re.search(r'/admin/inquiries/(\d+)', inbox).group(1))
    stored = get(f'/admin/inquiries/{inquiry_id}').text
    assert '&lt;script&gt;evil()&lt;/script&gt;' in stored and '<script>evil()' not in stored
    assert 'QA network' in stored and 'QA business' in stored
    post(f'/admin/inquiries/{inquiry_id}/status', {'status': 'in_progress'}, f'/admin/inquiries/{inquiry_id}')
    assert reference in get('/admin/inquiries?status=in_progress').text
    assert reference not in get('/admin/inquiries?status=resolved').text
    post(f'/admin/inquiries/{inquiry_id}/status', {'status': 'in_progress', 'internal_notes': '=2+2'}, f'/admin/inquiries/{inquiry_id}')
    assert '=2+2' in get(f'/admin/inquiries/{inquiry_id}').text
    post(f'/admin/inquiries/{inquiry_id}/status', {'status': 'resolved'}, f'/admin/inquiries/{inquiry_id}')
    assert '=2+2' in get(f'/admin/inquiries/{inquiry_id}').text  # status-only updates preserve notes
    exported = get('/admin/inquiries/export?q=' + reference)
    assert exported.headers['Content-Type'].startswith('text/csv')
    exported_rows = list(csv.DictReader(io.StringIO(exported.content.decode('utf-8-sig'))))
    assert len(exported_rows) == 1 and exported_rows[0]['Reference'] == reference
    assert exported_rows[0]['Internal Notes'] == "'=2+2" and exported_rows[0]['Phone'].startswith("'+")
    assert public.get(BASE + '/admin/inquiries/export', allow_redirects=False).status_code == 302
    assert admin.get(BASE + '/admin/inquiries/export?from=2026-12-31&to=2026-01-01').status_code == 422
    assert 'end date' in get('/admin/inquiries?from=2026-12-31&to=2026-01-01').text
    token('/contact', public)
    time.sleep(2.1)
    for i in range(4):
        response = post('/inquiry', dict(inquiry, kind='complaint' if i == 0 else 'general', batch_number='QA-BATCH'), '/contact', public)
        assert 'Inquiry received.' in response.text
        if i < 3:
            token('/contact', public)
            time.sleep(2.1)
    token('/contact', public)
    time.sleep(2.1)
    response = post('/inquiry', dict(inquiry, kind='general'), '/contact', public, error=True)
    assert 'Submission limit reached' in response.text
    post(f'/admin/inquiries/{inquiry_id}/delete', {}, f'/admin/inquiries/{inquiry_id}')
    assert reference not in get('/admin/inquiries').text

    root = ET.fromstring(get('/sitemap.xml', public).text)
    locations = [element.text for element in root.iter('{http://www.sitemaps.org/schemas/sitemap/0.9}loc')]
    assert any(f'/products/{product_id}?lang=my' in value for value in locations)
    assert 'Disallow: /admin' in get('/robots.txt', public).text
    draft = dict(product, id=product_id)
    draft.pop('published')
    post('/admin/products/save', draft, f'/admin/products/{product_id}/edit')
    assert public.get(BASE + f'/products/{product_id}').status_code == 404
    assert f'/products/{product_id}?' not in get('/sitemap.xml', public).text
    assert 'Staff preview' in get(f'/admin/preview/products/{product_id}').text
    assert 'noindex,nofollow' in get(f'/admin/preview/products/{product_id}').text
    assert public.get(BASE + f'/admin/preview/products/{product_id}', allow_redirects=False).status_code == 302
    assert 'Activity history' in get('/admin/activity').text
    assert 'QA Superking sample' in get('/admin/activity?q=QA+Superking+sample').text
    from admin_cases import check_content_workflows
    check_content_workflows(BASE, admin, public, get, post, record_id, category, png, pdf)
    from product_qr_series import prepare as prepare_qr_series
    qr_series_ids = prepare_qr_series(BASE, admin, public, get, post, record_id, category)
    print('PASS: public routes, localization, search/filters, CMS, publication checks, sanitization, uploads, inquiries, CSRF, spam limits and SEO.')

    from playwright.sync_api import sync_playwright
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(channel='msedge', headless=True)
        page = browser.new_page(viewport={'width': 1440, 'height': 960})
        page.set_default_timeout(10000)
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        for width, height in [(1440, 960), (1024, 768), (390, 844), (320, 700)]:
            page.set_viewport_size({'width': width, 'height': height})
            for path in routes + ['/?lang=my', '/products?q=QA', f'/articles/{article_id}']:
                page.goto(BASE + path)
                assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, path)
        page.set_viewport_size({'width': 390, 'height': 844})
        page.goto(BASE)
        page.get_by_role('button', name='Open navigation').click()
        assert page.get_by_role('navigation', name='Main navigation').is_visible()
        page.keyboard.press('Escape')
        assert page.locator('.menu-toggle').get_attribute('aria-expanded') == 'false'
        page.locator('.language-switch').click()
        assert page.locator('html').get_attribute('lang') == 'my'
        page.get_by_role('link', name='Switch to English').click()
        assert page.locator('html').get_attribute('lang') == 'en'
        page.goto(BASE + '/admin/login')
        page.get_by_label('Email address').fill('admin@superking.local')
        page.get_by_label('Password', exact=True).fill(password)
        page.get_by_role('button', name='Sign in').click()
        page.wait_for_url(BASE + '/admin')
        from product_qr import check_browser as check_qr_browser
        check_qr_browser(page, BASE, product_id, out)
        from product_qr_series import check_browser as check_qr_series
        check_qr_series(page, BASE, qr_series_ids, out)
        for width, height in [(1440, 960), (1280, 720), (1024, 768), (390, 844), (320, 700)]:
            page.set_viewport_size({'width': width, 'height': height})
            for path in ['/admin', '/admin/articles/create', '/admin/dealers/create', '/admin/documents/create', '/admin/manufacturing_updates/create', '/admin/settings', '/admin/account', '/admin/inquiries', '/admin/products', f'/admin/products/{product_id}/edit']:
                page.goto(BASE + path)
                assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, path)
                assert page.locator('.admin-content p, .admin-content label, .admin-content input:not([type=hidden]):not([type=checkbox]), .admin-content td, .admin-content td strong').evaluate_all('elements => elements.filter(element => element.getClientRects().length).every(element => parseFloat(getComputedStyle(element).fontSize) >= 14)'), (width, path, 'Admin text is too small')
        page.set_viewport_size({'width': 1440, 'height': 960})
        page.goto(BASE + '/admin')
        page.screenshot(path=str(out / 'platform-admin-desktop.png'), full_page=True)
        page.screenshot(path=str(out / 'admin-dashboard-preview.png'))
        page.goto(BASE + '/admin/inquiries')
        page.screenshot(path=str(out / 'admin-inbox-desktop.png'), full_page=True)
        page.goto(BASE + '/admin')
        page.get_by_role('button', name='Dark mode', exact=True).click()
        assert page.locator('body').get_attribute('data-theme') == 'dark'
        page.goto(BASE + '/admin/articles/create')
        page.screenshot(path=str(out / 'platform-admin-article-dark.png'), full_page=True)
        page.get_by_role('button', name='Comfortable', exact=True).click()
        assert page.locator('body').get_attribute('data-density') == 'comfortable'
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        page.set_viewport_size({'width': 390, 'height': 844})
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        page.goto(BASE + '/admin')
        page.screenshot(path=str(out / 'admin-dashboard-mobile.png'))
        page.get_by_role('button', name='Toggle sidebar').click()
        assert page.locator('body').evaluate("element => element.classList.contains('sidebar-open')")
        page.get_by_role('button', name='Close navigation').click()
        assert not page.locator('body').evaluate("element => element.classList.contains('sidebar-open')")
        page.get_by_role('button', name='Toggle sidebar').click()
        page.keyboard.press('Escape')
        assert page.locator('#sidebar-toggle').get_attribute('aria-expanded') == 'false'
        page.goto(BASE + '/admin/articles?q=QA+news')
        news_row = page.get_by_role('row').filter(has_text='QA news')
        news_row.get_by_role('button', name='Delete QA news', exact=True).click()
        assert page.get_by_role('dialog').is_visible()
        page.get_by_role('button', name='Cancel', exact=True).click()
        assert news_row.count() == 1
        news_row.get_by_role('button', name='Delete QA news', exact=True).click()
        with page.expect_navigation(wait_until='domcontentloaded'):
            page.get_by_role('button', name='Delete record', exact=True).click()
        page.wait_for_url('**/admin/articles?**')
        assert 'q=QA' in page.url
        assert page.get_by_role('row').filter(has_text='QA news').count() == 0
        page.set_viewport_size({'width': 1440, 'height': 900})
        page.goto(BASE + '/admin/products/create')
        editor = page.get_by_role('textbox', name='Features & characteristics', exact=True)
        editor.fill('Readable formatted content')
        editor.press('Control+a')
        page.get_by_role('button', name='Bold', exact=True).first.click()
        assert '<b>' in editor.inner_html() or '<strong>' in editor.inner_html()
        page.goto(BASE + '/admin/articles/create')
        page.get_by_label('Article title', exact=False).fill('Browser workflow article')
        page.get_by_label('Article type', exact=True).select_option('news')
        page.get_by_role('textbox', name='Article content', exact=True).fill('Browser saved article content')
        page.get_by_role('button', name='Save & keep editing', exact=True).click()
        page.wait_for_url('**/admin/articles/*/edit?**')
        assert page.get_by_label('Article title', exact=False).input_value() == 'Browser workflow article'
        page.get_by_label('Related product (optional)', exact=True).select_option(str(product_id))
        page.get_by_role('button', name='Save changes', exact=True).click()
        page.wait_for_url('**/admin/articles')
        assert page.get_by_role('row').filter(has_text='Browser workflow article').is_visible()
        page.goto(BASE + '/admin/products/create')
        page.locator('#image').set_input_files({'name': 'preview.png', 'mimeType': 'image/png', 'buffer': png})
        assert page.get_by_role('img', name='Selected image preview', exact=True).is_visible()
        assert 'Save to upload' in page.locator('.upload-feedback').first.inner_text()
        page.locator('#image').set_input_files({'name': 'script.html', 'mimeType': 'text/html', 'buffer': b'<script>bad</script>'})
        assert not page.locator('#image').evaluate('element => element.checkValidity()')
        assert not errors, errors
        browser.close()
    print('PASS: desktop/tablet/mobile overflow, language links, menu/Escape, admin forms, theme/density and browser errors.')
    from admin_cases import check_account_workflow
    check_account_workflow(BASE, admin, get, post, password)
finally:
    if server is not None:
        server.terminate()
        server.wait(timeout=10)
    if log is not None:
        log.close()
    # The database name is generated above, checked against a strict prefix, and
    # passed via process environment. Never drop a configured company database.
    php('-r', "require 'bootstrap/app.php'; $qaName=getenv('DB_DATABASE'); if(!preg_match('/^superking_qa_[0-9]+$/',$qaName))exit(1); foreach(Mini\\Database::fetchAll('SELECT image path FROM products UNION SELECT data_sheet FROM products UNION SELECT safety_sheet FROM products UNION SELECT file FROM documents UNION SELECT logo FROM settings UNION SELECT favicon FROM settings') as $qaRow){$qaFile=$qaRow['path']; if(is_string($qaFile)&&preg_match('#^uploads/[a-f0-9]{32}\\.(png|jpg|webp|pdf)$#',$qaFile)&&is_file(BASE_PATH.'/public/'.$qaFile))unlink(BASE_PATH.'/public/'.$qaFile);} Mini\\Database::connection(true)->exec('DROP DATABASE IF EXISTS `'.$qaName.'`');")
