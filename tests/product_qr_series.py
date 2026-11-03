"""Batch selection, physical label layout and saved print setting checks."""
import re
from urllib.parse import parse_qs, urlsplit


def prepare(base, admin, public, get, post, record_id, category):
    ids = []
    for index in range(22):
        name = f'QR series {index + 1:02d}'
        post('/admin/products/save', {'name': name, 'category_id': category, 'product_code': f'QR-{index + 1:02d}'}, '/admin/products/create')
        ids.append(record_id('products', name))
    path = '/admin/products/qr'
    assert public.get(base + path + '?ids=' + str(ids[0]), allow_redirects=False).status_code == 302
    response = get(path + f'?ids={ids[1]},{ids[0]},{ids[1]}')
    assert response.text.index(f'data-id="{ids[1]}"') < response.text.index(f'data-id="{ids[0]}"')
    assert response.text.count('data-id=') == 2
    context = f'ids={ids[1]},{ids[0]}&mode=batch&return_to=admin/products%3Fq%3DQR%2Bseries%26page%3D2'
    failed = post('/admin/account?' + context, {'email': 'qr-settings@example.com', 'current_password': 'incorrect'}, '/admin/settings?tab=account&' + context, error=True)
    returned = parse_qs(urlsplit(failed.url).query)
    assert returned['tab'] == ['account'] and returned['ids'] == [f'{ids[1]},{ids[0]}']
    failed = post('/admin/settings?' + context, {'company_name': 'QR test', 'theme_color': 'bad'}, '/admin/settings?tab=brand&' + context, error=True)
    returned = parse_qs(urlsplit(failed.url).query)
    assert returned['tab'] == ['brand'] and returned['ids'] == [f'{ids[1]},{ids[0]}']
    for suffix in ['', '?ids=', '?ids[]=1', '?ids=0', '?ids=1,invalid', '?ids=999999999', '?ids=' + ','.join(['1'] * 201)]:
        response = admin.get(base + path + suffix, allow_redirects=False)
        assert response.status_code == 302, (suffix, response.status_code)
        assert response.headers['Location'].endswith('/admin/products')
    return ids


def check_browser(page, base, ids, out):
    import cv2
    import numpy as np

    def ready(tab):
        tab.wait_for_function("document.body.dataset.printReady === 'true'")

    def scan(locator, expected):
        pixels = locator.screenshot()
        decoded, _, _ = cv2.QRCodeDetector().detectAndDecode(cv2.imdecode(np.frombuffer(pixels, dtype=np.uint8), cv2.IMREAD_GRAYSCALE))
        assert decoded == expected, (decoded, expected)

    page.goto(base + '/admin/products?q=QR+series&sort=name')
    assert page.get_by_role('button', name='Print selected QR codes').is_disabled()
    page.get_by_role('checkbox', name='Select all products on this page').check()
    assert page.locator('#qr-selection-count').inner_text() == '20 products selected'
    # Header deselection only changes the current page, and accurately reflects a partial selection.
    page.locator('.qr-product-select').first.uncheck()
    assert page.locator('#select-page-qr').evaluate('node => node.indeterminate')
    page.locator('.qr-product-select').first.check()
    page.goto(base + '/admin/products?q=QR+series&sort=name&page=2')
    assert page.locator('#qr-selection-count').inner_text() == '20 products selected'
    page.get_by_role('checkbox', name='Select all products on this page').check()
    assert page.locator('#qr-selection-count').inner_text() == '22 products selected'
    # Selection order reflects deselecting/reselecting the first product above.
    selected_order = ids[1:20] + [ids[0]] + ids[20:]
    with page.expect_popup() as opened:
        page.get_by_role('button', name='Print selected QR codes').click()
    batch = opened.value
    ready(batch)
    assert [int(value) for value in parse_qs(urlsplit(batch.url).query)['ids'][0].split(',')] == selected_order
    assert batch.locator('#qr-settings').count() == 0
    batch.get_by_role('link', name='QR print settings', exact=True).click()
    assert batch.locator('#qr-preview').count() == 0
    assert parse_qs(urlsplit(batch.url).query)['tab'] == ['qr']
    sections = batch.get_by_role('navigation', name='Settings sections')
    assert sections.get_by_role('link', name='QR printing', exact=True).get_attribute('aria-current') == 'page'
    sections.get_by_role('link', name='Brand & website', exact=True).click()
    assert batch.locator('input[name=company_name]').is_visible()
    batch.get_by_role('navigation', name='Settings sections').get_by_role('link', name='Your account', exact=True).click()
    assert batch.locator('#current_password').is_visible()
    assert batch.get_by_role('navigation', name='Admin navigation').get_by_role('link', name='Settings', exact=True).count() == 1
    assert batch.get_by_role('navigation', name='Admin navigation').get_by_role('link', name='Your account', exact=True).count() == 0
    batch.get_by_role('navigation', name='Settings sections').get_by_role('link', name='QR printing', exact=True).click()
    assert parse_qs(urlsplit(batch.url).query)['ids'][0] == ','.join(map(str, selected_order))
    batch.locator('#qr-size').fill('50')
    batch.get_by_role('link', name='Cancel', exact=True).click()
    ready(batch)
    assert abs(batch.locator('.qr-code').first.bounding_box()['width'] * 25.4 / 96 - 35) < .1
    batch.get_by_role('link', name='QR print settings', exact=True).click()
    batch.locator('#per-page').fill('6')
    batch.locator('#copies').fill('2')
    batch.locator('#qr-size').fill('30')
    batch.get_by_role('button', name='Save and return to preview').click()
    ready(batch)
    assert batch.locator('.product-label').count() == 44
    assert batch.locator('.print-sheet').count() == 8
    assert batch.locator('.print-sheet').first.locator('.product-label').count() == 6
    assert batch.locator('.print-sheet').last.locator('.product-label').count() == 2
    actual = batch.locator('.product-label').evaluate_all('nodes => nodes.map(node => Number(node.dataset.productId))')
    assert actual == [ident for ident in selected_order for _ in range(2)]
    dimension = batch.locator('.qr-code').first.bounding_box()['width'] * 25.4 / 96
    assert abs(dimension - 30) < .1
    scan(batch.locator('.qr-code').first, base + f'/products/{selected_order[0]}')
    scan(batch.locator('.qr-code').last, base + f'/products/{selected_order[-1]}')
    pdf = batch.pdf(path=str(out / 'product-qr-series.pdf'), prefer_css_page_size=True)
    assert len(re.findall(rb'/Type\s*/Page\b', pdf)) == 8
    batch.screenshot(path=str(out / 'product-qr-series-preview.png'), full_page=False)
    # All label content and layout settings survive reopening/reloading this browser.
    batch.get_by_role('link', name='QR print settings', exact=True).click()
    batch.get_by_label('Company name', exact=True).uncheck()
    batch.get_by_label('Website address', exact=True).uncheck()
    batch.get_by_label('Label border', exact=True).check()
    batch.locator('#label-caption').fill('Scan this product <b>details</b>')
    batch.locator('#label-font').fill('11')
    batch.get_by_role('button', name='Save and return to preview').click()
    ready(batch)
    batch.reload()
    ready(batch)
    assert batch.locator('.brand').count() == 0
    assert batch.locator('.product-url').count() == 0
    assert batch.locator('.scan-caption').first.inner_text() == 'Scan this product <b>details</b>'
    assert batch.locator('.scan-caption b').count() == 0
    assert 'with-border' in batch.locator('#qr-preview').get_attribute('class')

    batch.get_by_role('link', name='QR print settings', exact=True).click()
    assert batch.locator('#per-page').input_value() == '6'
    assert batch.locator('#copies').input_value() == '2'
    batch.locator('#qr-size').fill('100')
    batch.locator('#per-page').fill('100')
    batch.get_by_role('button', name='Save and return to preview').click()
    batch.wait_for_function("document.querySelector('#qr-status').dataset.error === 'true'")
    assert batch.locator('#print-qr').is_disabled()
    batch.emulate_media(media='print')
    assert not batch.locator('main').is_visible()
    batch.emulate_media(media='screen')
    batch.get_by_role('link', name='QR print settings', exact=True).click()
    batch.locator('#copies').fill('100')
    batch.get_by_role('button', name='Save and return to preview').click()
    batch.wait_for_function("document.querySelector('#qr-status').textContent.includes('1,000')")
    batch.get_by_role('link', name='QR print settings', exact=True).click()
    batch.get_by_role('button', name='Reset settings').click()
    assert batch.locator('#copies').input_value() == '1'
    batch.get_by_role('button', name='Save and return to preview').click()
    ready(batch)

    for paper in ['a5', 'letter', 'custom']:
        batch.get_by_role('link', name='QR print settings', exact=True).click()
        batch.locator('#paper-size').select_option(paper)
        batch.locator('#per-page').fill('2')
        batch.locator('#qr-size').fill('25')
        batch.get_by_role('button', name='Save and return to preview').click()
        ready(batch)
        assert batch.locator('.print-sheet').count() == 11
        if paper == 'custom':
            batch.get_by_role('link', name='QR print settings', exact=True).click()
            assert batch.locator('#page-width').is_visible()
            batch.locator('#page-width').fill('110')
            batch.locator('#page-height').fill('160')
            batch.get_by_role('button', name='Save and return to preview').click()
            ready(batch)
            pdf = batch.pdf(prefer_css_page_size=True)
            box = re.search(rb'/MediaBox\s*\[([\d.\s]+)\]', pdf)
            dimensions = [float(value) for value in box.group(1).split()]
            assert abs(dimensions[2] - 110 * 72 / 25.4) < 1
            assert abs(dimensions[3] - 160 * 72 / 25.4) < 1
    batch.get_by_role('link', name='QR print settings', exact=True).click()
    batch.locator('#orientation').select_option('landscape')
    for width in [320, 390, 1440]:
        batch.set_viewport_size({'width': width, 'height': 960})
        assert batch.evaluate('document.documentElement.scrollWidth <= innerWidth')
    batch.screenshot(path=str(out / 'qr-settings-page.png'), full_page=True)
    batch.get_by_role('button', name='Dark mode', exact=True).click()
    assert batch.locator('body').get_attribute('data-theme') == 'dark'
    batch.screenshot(path=str(out / 'qr-settings-dark.png'), full_page=True)
    batch.get_by_role('button', name='Light mode', exact=True).click()
    batch.get_by_role('button', name='Save and return to preview').click()
    ready(batch)
    box = batch.locator('.print-sheet').first.bounding_box()
    assert box['width'] > box['height']
    for width in [320, 390, 1440]:
        batch.set_viewport_size({'width': width, 'height': 960})
        assert batch.evaluate('document.documentElement.scrollWidth <= innerWidth')
    batch.get_by_role('link', name='Back to products').click()
    returned = parse_qs(urlsplit(batch.url).query)
    assert returned['q'] == ['QR series'] and returned['page'] == ['2']
    assert batch.locator('#qr-selection-count').inner_text() == '22 products selected'
    batch.get_by_role('button', name='Clear selection').click()
    assert batch.get_by_role('button', name='Print selected QR codes').is_disabled()
    batch.close()
    page.reload()
    assert page.locator('#qr-selection-count').inner_text() == '22 products selected'
    page.get_by_role('button', name='Clear selection').click()
    page.get_by_role('link', name='QR print settings', exact=True).click()
    page.locator('#qr-size').fill('30')
    page.get_by_role('button', name='Save settings', exact=True).click()
    assert page.locator('#settings-status').inner_text() == 'QR print settings saved in this browser.'
    page.get_by_role('link', name='Back to products').click()
    assert parse_qs(urlsplit(page.url).query)['page'] == ['2']
    page.evaluate("localStorage.removeItem('sk-qr-print-settings-v1')")
    print('PASS: cross-page batch selection/order, copies, exact QR size, 8-page PDF, saved labels, paper sizes, landscape, invalid layouts and mobile preview.')
