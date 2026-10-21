"""Read-only responsive/browser checks against the local company website."""
import os
from pathlib import Path
from playwright.sync_api import sync_playwright

BASE = os.environ.get('SK_TEST_URL', 'http://localhost/superking-website').rstrip('/')
out = Path(__file__).resolve().parents[1] / 'storage/qa'
out.mkdir(parents=True, exist_ok=True)
routes = ['/', '/company', '/products', '/manufacturing', '/knowledge', '/news', '/dealers', '/distributor', '/contact', '/privacy']
with sync_playwright() as playwright:
    browser = playwright.chromium.launch(channel='msedge', headless=True)
    page = browser.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    for width, height in [(1920, 1080), (1440, 960), (1280, 800), (1100, 800), (1024, 768), (768, 1024), (390, 844), (320, 700)]:
        page.set_viewport_size({'width': width, 'height': height})
        for path in routes + [path + '?lang=my' for path in routes]:
            response = page.goto(BASE + path)
            assert response.status == 200, (path, response.status)
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (width, path)
            too_small = page.locator('main p, main label, main .btn, main .eyebrow, .range-number, .card-bottom, .footer-grid a, .footer-bottom').evaluate_all("elements => elements.filter(element => element.getClientRects().length && parseFloat(getComputedStyle(element).fontSize) < 14).map(element => ({text: element.textContent.slice(0, 60), size: getComputedStyle(element).fontSize}))")
            assert not too_small, (width, path, too_small)
            padding = page.locator('main > .section').first.evaluate('element => parseFloat(getComputedStyle(element).paddingTop)')
            assert padding <= (32 if width <= 760 else 48), (width, path, padding)
            if width <= 760:
                assert page.locator('main input:not([type=hidden]):not([type=checkbox]), main select, main textarea').evaluate_all('elements => elements.every(element => parseFloat(getComputedStyle(element).fontSize) >= 16)'), (width, path)
            page.locator('img[loading=lazy]').evaluate_all("images => images.forEach(image => image.loading = 'eager')")
            page.wait_for_function("Array.from(document.images).every(image => image.complete && image.naturalWidth > 0)", timeout=15000)
            assert not page.locator('img').evaluate_all('(images) => images.some(image => !image.complete || image.naturalWidth === 0)'), path
        page.goto(BASE + '/')
        if width in [1440, 390]:
            page.screenshot(path=str(out / ('concept-home-desktop.png' if width == 1440 else 'concept-home-mobile.png')), full_page=True)
            page.screenshot(path=str(out / ('readability-desktop.png' if width == 1440 else 'readability-mobile.png')))
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
    page.goto(BASE + '/products')
    page.get_by_label('Search products', exact=True).fill('readability-check-missing-product')
    page.locator('.catalogue-filters').get_by_role('button', name='Search', exact=False).click()
    assert 'readability-check-missing-product' in page.url
    page.locator('.catalogue-filters').get_by_role('link', name='Reset', exact=True).click()
    assert page.get_by_label('Search products', exact=True).input_value() == ''
    page.go_back()
    assert page.get_by_label('Search products', exact=True).input_value() == 'readability-check-missing-product'
    page.goto(BASE + '/distributor')
    page.get_by_role('button', name='Send Inquiry', exact=False).click()
    assert page.locator('#full_name').evaluate('(element) => !element.validity.valid')
    page.set_viewport_size({'width': 1440, 'height': 960})
    page.goto(BASE + '/knowledge')
    page.screenshot(path=str(out / 'concept-knowledge-desktop.png'), full_page=True)
    assert not errors, errors
    browser.close()
print('PASS: all public pages in both languages at eight widths; readable type, compact spacing, images, navigation, search/reset/Back, required fields and browser errors.')
