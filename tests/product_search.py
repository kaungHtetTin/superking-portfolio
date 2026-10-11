"""Read-only regression checks for public product search on the seeded catalogue."""
import os,re,requests
base=os.environ.get('SK_TEST_URL','http://localhost/superking-website')
def search(query,category=None):
    params={'q':query}
    if category is not None:params['category']=category
    r=requests.get(base+'/products',params=params)
    assert r.status_code==200,(r.status_code,r.text[:300])
    count=int(re.search(r'class="results-line"><span>(\d+) product',r.text).group(1))
    return count,r.text
assert search('industrial demo')[0]==2
assert search('INDUSTRIAL')[0]==2
assert search('demo industrial',3)[0]==2
assert search('industrial',1)[0]==0
assert search('unlikely-product-search-8137')[0]==0
assert search('%')[0]==0
assert search('_')[0]==0
assert search("' OR 1=1 --")[0]==0
count,html=search('  industrial   demo  ')
assert count==2 and 'value="industrial   demo"' in html
assert 'Search the product catalogue' in requests.get(base+'/company').text
r=requests.get(base+'/products',params={'q[]':'bad'})
assert r.status_code==200 and 'Array to string conversion' not in r.text
print('PASS: multiword and case-insensitive search, category combinations, empty results, literal wildcards, safe input and shared search form.')
