"""Additional admin workflows, called only by the disposable specification suite."""
import html
import re
from urllib.parse import urlencode


def check_content_workflows(base, admin, public, get, post, record_id, category, png, pdf):
    records = {}
    cases = {
        'categories': {'name': 'Workflow category', 'description': 'Workflow category description', 'sort_order': 3},
        'products': {'name': 'Workflow product', 'category_id': category, 'summary': 'Workflow product summary', 'benefits': '<p>Documented test content</p>', 'applications': '<p>Test equipment</p>'},
        'slides': {'title': 'Workflow banner', 'description': 'Workflow banner description', 'button_text': '', 'button_url': '', 'sort_order': 3},
        'company_sections': {'title': 'Workflow company section', 'description': '<p>Workflow company body</p>', 'sort_order': 3},
        'contacts': {'title': 'Workflow office', 'email': 'workflow@example.com', 'phone': '+95 12345', 'address': 'Workflow address', 'hours': 'By appointment', 'sort_order': 3},
        'articles': {'title': 'Workflow article', 'description': '<p>Workflow article body</p>', 'summary': 'Workflow article summary', 'kind': 'news', 'topic': '', 'sort_order': 3},
        'manufacturing_updates': {'title': 'Workflow factory update', 'description': '<p>Workflow verified update</p>', 'stage': 'planned', 'verified': 1, 'sort_order': 3},
        'dealers': {'title': 'Workflow dealer', 'description': 'Workflow dealer description', 'region': 'Workflow region', 'city': 'Workflow city', 'township': 'Workflow township', 'address': 'Workflow street', 'phone': '+95 12345', 'verified': 1, 'sort_order': 3},
        'documents': {'title': 'Workflow document', 'description': 'Workflow document description', 'document_type': 'catalogue', 'verified': 1, 'sort_order': 3},
    }
    for kind, data in cases.items():
        name_key = 'name' if kind in ['products', 'categories'] else 'title'
        if kind == 'products':
            data['category_id'] = records['categories']
        files = {'file': ('workflow.pdf', pdf, 'application/pdf')} if kind == 'documents' else None
        if kind == 'products':
            files = {'image': ('workflow.png', png, 'image/png'), 'data_sheet': ('workflow.pdf', pdf, 'application/pdf')}
        post('/admin/' + kind + '/save', data, '/admin/' + kind + '/create', files=files)
        ident = record_id(kind, data[name_key])
        records[kind] = ident
        if kind != 'categories':
            assert data[name_key] in get('/admin/' + kind + '?status=draft&q=Workflow').text
            assert data[name_key] not in get('/admin/' + kind + '?status=published&q=Workflow').text
        context = 'admin/' + kind + '?' + urlencode({'q': 'Workflow', 'status': 'draft', 'sort': 'oldest', 'page': 1})
        data.update(id=ident, return_to=context, _action='stay')
        data[name_key] += ' edited'
        saved = post('/admin/' + kind + '/save', data, f'/admin/{kind}/{ident}/edit')
        assert f'/admin/{kind}/{ident}/edit?' in saved.url
        assert data[name_key] in saved.text
        assert html.escape(context, quote=True) in saved.text
        if kind in ['products', 'articles']:
            assert 'Staff preview' in get(f'/admin/preview/{kind}/{ident}').text
            public_path = '/products/' if kind == 'products' else '/articles/'
            assert public.get(base + public_path + str(ident)).status_code == 404
        data['_action'] = 'close'
        saved = post('/admin/' + kind + '/save', data, f'/admin/{kind}/{ident}/edit')
        assert saved.url == base + '/' + context
        if kind != 'categories':
            data['published'] = 1
            data['return_to'] = 'admin/' + kind + '?status=published'
            post('/admin/' + kind + '/save', data, f'/admin/{kind}/{ident}/edit')
            destination = {'products': '/products/' + str(ident), 'slides': '/', 'company_sections': '/company', 'contacts': '/contact', 'articles': '/articles/' + str(ident), 'manufacturing_updates': '/manufacturing', 'dealers': '/dealers?q=Workflow', 'documents': '/distributor'}[kind]
            assert data[name_key] in get(destination, public).text, kind

    protected = post(f'/admin/categories/{records["categories"]}/delete', {}, '/admin/categories')
    assert 'Move or delete the products' in protected.text
    assert 'Workflow category edited' in get('/admin/categories?q=Workflow').text
    post('/admin/categories/save', {'name': 'Workflow category edited', 'description': 'Duplicate', 'sort_order': 0}, '/admin/categories/create', error=True)
    post('/admin/products/save', {'id': ['bad'], 'name': 'Invalid'}, '/admin/products/create', error=True)
    get('/admin/products?q[]=bad&status[]=bad&category[]=bad&sort[]=bad')

    # File replacement/removal must clean up the replaced files as well as links.
    prod = cases['products']
    detail = get('/products/' + str(records['products']), public).text
    previous = re.search(r'src="[^\"]*/(uploads/[a-f0-9]+\.png)"', detail).group(1)
    post('/admin/products/save', prod, f'/admin/products/{records["products"]}/edit', files={'image': ('replacement.png', png, 'image/png')})
    assert public.get(base + '/' + previous).status_code == 404
    prod['remove_image'] = 1
    post('/admin/products/save', prod, f'/admin/products/{records["products"]}/edit')
    assert 'Product photograph not yet available' in get('/products/' + str(records['products']), public).text

    # Deleting a product clears its optional article relationship.
    article = cases['articles']
    article['related_product_id'] = records['products']
    post('/admin/articles/save', article, f'/admin/articles/{records["articles"]}/edit')
    post(f'/admin/products/{records["products"]}/delete', {}, '/admin/products')
    form = get(f'/admin/articles/{records["articles"]}/edit').text
    assert f'value="{records["products"]}" selected' not in form
    article['related_product_id'] = ''
    post('/admin/articles/save', article, f'/admin/articles/{records["articles"]}/edit')

    # Multi-page results keep the query/sort in their next/back links.
    paging_ids = []
    for index in range(21):
        title = f'Paging workflow {index:02d}'
        post('/admin/categories/save', {'name': title, 'description': 'Pagination test', 'sort_order': index}, '/admin/categories/create')
        paging_ids.append(record_id('categories', title))
    first = get('/admin/categories?q=Paging+workflow&sort=oldest').text
    assert '21 records' in first and 'page=2' in first and 'sort=oldest' in first
    second = get('/admin/categories?q=Paging+workflow&sort=oldest&page=2').text
    assert 'Paging workflow 20' in second and 'Page 2 of 2' in second
    assert 'Paging workflow 00' not in second
    for ident in paging_ids:
        post(f'/admin/categories/{ident}/delete', {}, '/admin/categories')

    for kind in reversed(cases):
        if kind == 'products':
            continue
        data = cases[kind]
        name_key = 'name' if kind == 'categories' else 'title'
        post(f'/admin/{kind}/{records[kind]}/delete', {}, '/admin/' + kind)
        assert data[name_key] not in get('/admin/' + kind + '?q=Workflow').text

    settings = {'company_name': 'Workflow Brand', 'tagline': 'Workflow tagline', 'theme_color': '#C1121F', 'intro': 'Workflow company introduction', 'intro_my': '', 'meta_description': 'Workflow SEO description'}
    post('/admin/settings', dict(settings, theme_color='bad'), '/admin/settings', error=True)
    post('/admin/settings', settings, '/admin/settings', files={'logo': ('logo.png', png, 'image/png'), 'favicon': ('favicon.png', png, 'image/png')})
    home = get('/', public).text
    assert 'Workflow SEO description' in home
    assert 'Workflow company introduction' in home and 'Workflow Brand' in home
    assert 'uploads/' in home
    post('/admin/settings', dict(settings, remove_logo=1, remove_favicon=1), '/admin/settings')
    assert 'superking-logo.webp' in get('/', public).text
    assert 'Brand & website settings' in html.unescape(get('/admin/activity?type=settings').text)
    post('/admin/settings', dict(settings, company_name='SUPERKING LUBRICANTS', intro='SUPERKING LUBRICANTS has been imported from the UAE and distributed in Myanmar since 2016 by Royal Myanmar King Company Limited.', meta_description=''), '/admin/settings')
    assert 'New inquiries' in get('/admin').text
    print('PASS: all nine CMS create/edit/publish/delete flows, filtered returns, previews, pagination, upload replacement/removal, settings propagation and activity history.')


def check_account_workflow(base, admin, get, post, password):
    new_email = 'workflow-admin@example.com'
    post('/admin/account', {'email': new_email, 'current_password': 'incorrect'}, '/admin/account', error=True)
    post('/admin/account', {'email': new_email, 'current_password': password, 'new_password': '', 'confirm_password': ''}, '/admin/account')
    assert new_email in get('/admin/account').text
    new_password = 'Workflow-secure-test-password-2026'
    post('/admin/account', {'email': new_email, 'current_password': password, 'new_password': new_password, 'confirm_password': 'mismatch'}, '/admin/account', error=True)
    post('/admin/account', {'email': new_email, 'current_password': password, 'new_password': new_password, 'confirm_password': new_password}, '/admin/account')
    post('/admin/logout', {}, '/admin/account')
    assert admin.get(base + '/admin', allow_redirects=False).status_code == 302
    post('/admin/login', {'email': new_email, 'password': password}, '/admin/login', error=True)
    post('/admin/login', {'email': new_email, 'password': new_password}, '/admin/login')
    assert get('/admin').url.endswith('/admin')
    print('PASS: current-password checks, email-only account changes, password validation/rotation, logout and new-credential login.')
