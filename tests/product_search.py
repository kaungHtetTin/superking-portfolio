"""Read-only product search checks, independent of unverified starter demo data."""
import os
import re
import requests

BASE = os.environ.get('SK_TEST_URL', 'http://localhost/superking-website').rstrip('/')
for query in ['unlikely-product-search-8137', '%', '_', "' OR 1=1 --"]:
    response = requests.get(BASE + '/products', params={'q': query}, timeout=10)
    assert response.status_code == 200
    count = int(re.search(r'class="results-line"><span>(\d+) product', response.text).group(1))
    assert count == 0, query
response = requests.get(BASE + '/products', params={'q[]': 'bad', 'category[]': 'bad', 'viscosity[]': 'bad'}, timeout=10)
assert response.status_code == 200 and 'Array to string conversion' not in response.text
assert 'Search the product catalogue' in requests.get(BASE + '/company', timeout=10).text
for field in ['category', 'viscosity', 'specification', 'application']:
    assert 'name="' + field + '"' in response.text
print('PASS: empty search, literal wildcards, safe input, technical filters and shared product search.')
