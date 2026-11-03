"""Product QR checks used by the disposable specification suite.

QR scan verification requires opencv-python (and its numpy dependency).
"""
import html
import re
from urllib.parse import parse_qs, urlencode, urlsplit


def check_http(base, admin, public, get, product_id):
    path = f'/admin/products/{product_id}/qr'
    assert public.get(base + path, allow_redirects=False).status_code == 302
    context = 'admin/products?q=QA&status=published&sort=name&page=1'
    response = get(path + '?' + urlencode({'return_to': context}))
    assert f'data-url="/products/{product_id}"' in response.text
    assert '/admin/preview/' not in response.text
    assert html.escape('/' + context, quote=True) in response.text
    assert 'This product is a draft' not in response.text
    assert 'no-store' in response.headers.get('Cache-Control', '')
    for invalid in ['0', 'not-a-number', str(product_id) + 'bad', '999999999']:
        assert admin.get(base + f'/admin/products/{invalid}/qr').status_code == 404
    assert 'id="qr-settings"' not in response.text
    assert public.get(base + '/admin/products/qr-settings', allow_redirects=False).status_code == 302
    settings = get('/admin/products/qr-settings')
    assert 'id="qr-settings"' in settings.text and 'id="qr-preview"' not in settings.text
    unsafe_settings = get('/admin/products/qr-settings?return_to=https://example.com&ids[]=1')
    assert 'href="https://example.com"' not in unsafe_settings.text
    unsafe = get(path + '?return_to=https://example.com')
    assert 'href="https://example.com"' not in unsafe.text


def check_browser(page, base, product_id, out):
    import cv2
    import numpy as np

    context = '/admin/products?q=QA&status=draft&sort=name&page=1'
    page.goto(base + context)
    row = page.locator('tr').filter(has=page.locator(f'a[href*="/products/{product_id}/edit"]'))
    with page.expect_popup() as opened:
        row.get_by_role('link', name=re.compile('Print QR for')).click()
    qr_page = opened.value
    qr_page.get_by_role('button', name='Print QR code').wait_for(state='visible')
    assert qr_page.get_by_role('button', name='Print QR code').is_enabled()
    assert page.url == base + context
    assert 'This product is a draft' in qr_page.locator('.notice').first.inner_text()
    assert qr_page.locator('#local-url-notice').is_visible()
    expected = base + f'/products/{product_id}'
    assert qr_page.locator('#product-url').get_attribute('href') == expected
    pixels = qr_page.locator('#product-qr').screenshot()
    decoded, points, _ = cv2.QRCodeDetector().detectAndDecode(cv2.imdecode(np.frombuffer(pixels, dtype=np.uint8), cv2.IMREAD_GRAYSCALE))
    assert points is not None and decoded == expected, (decoded, expected)
    qr_page.screenshot(path=str(out / 'product-qr-preview.png'), full_page=True)
    qr_page.evaluate('window.print = () => { window.printRequested = true; }')
    qr_page.get_by_role('button', name='Print QR code').click()
    assert qr_page.evaluate('window.printRequested === true')
    qr_page.emulate_media(media='print')
    assert not qr_page.locator('.print-controls').is_visible()
    assert qr_page.locator('#product-qr svg').is_visible()
    qr_page.pdf(path=str(out / 'product-qr-label.pdf'), format='A4')
    qr_page.emulate_media(media='screen')
    for width in [320, 390, 1440]:
        qr_page.set_viewport_size({'width': width, 'height': 900})
        assert qr_page.evaluate('document.documentElement.scrollWidth <= innerWidth')
    qr_page.get_by_role('link', name='Back to products').click()
    assert urlsplit(qr_page.url).path == '/admin/products'
    returned = parse_qs(urlsplit(qr_page.url).query)
    assert all(returned[key] == value for key, value in parse_qs(urlsplit(context).query).items())
    qr_page.close()

    page.goto(base + f'/admin/products/{product_id}/edit')
    page.locator('#name').fill('Unsaved QR test edit')
    with page.expect_popup() as opened:
        page.get_by_role('link', name='Print QR', exact=True).click()
    opened.value.wait_for_load_state()
    assert page.locator('#name').input_value() == 'Unsaved QR test edit'
    opened.value.close()

    # Exercise both an Apache subdirectory URL and a configured public HTTPS base.
    qr_path = f'/admin/products/{product_id}/qr'
    for target in [f'/superking-website/products/{product_id}', f'https://superking.example/shop/products/{product_id}']:
        def configured_url(route):
            response = route.fetch()
            body = response.text().replace(f'data-url="/products/{product_id}"', f'data-url="{target}"')
            route.fulfill(response=response, body=body)
        page.route('**' + qr_path, configured_url)
        page.goto(base + qr_path)
        expected_target = base + target if target.startswith('/') else target
        assert page.locator('#product-url').get_attribute('href') == expected_target
        pixels = page.locator('#product-qr').screenshot()
        decoded, _, _ = cv2.QRCodeDetector().detectAndDecode(cv2.imdecode(np.frombuffer(pixels, dtype=np.uint8), cv2.IMREAD_GRAYSCALE))
        assert decoded == expected_target
        page.unroute('**' + qr_path)

    # The application script must fail visibly and keep printing disabled if the encoder cannot load.
    page.route('**/js/vendor/qrcodegen.js', lambda route: route.abort())
    page.goto(base + f'/admin/products/{product_id}/qr')
    assert page.get_by_role('button', name='Print QR code').is_disabled()
    assert 'Unable to generate' in page.locator('#qr-status').inner_text()
    page.unroute('**/js/vendor/qrcodegen.js')
    print('PASS: QR decoding, protected routes, draft notice, print/PDF, mobile layout, list filters, unsaved edits and encoder failure.')
