"""Run against the local preview with SK_ADMIN_PASSWORD set in the environment."""
import os, re, requests, io
from PIL import Image

BASE = os.environ.get('SK_TEST_URL', 'http://127.0.0.1:8000')
s = requests.Session()
def get(path):
    r=s.get(BASE+path); assert r.status_code==200, (path,r.status_code,r.text[:300]); return r
def token(path):
    return re.search(r'name="_token" value="([^"]+)"',get(path).text).group(1)
def post(path,data,source,files=None):
    data['_token']=token(source)
    r=s.post(BASE+path,data=data,files=files)
    assert r.status_code==200,(path,r.status_code)
    assert 'Application error' not in r.text and 'flash error' not in r.text,(path,r.text[-2000:])
    return r
for path in ['/','/company','/contact','/products','/products/1']:
    get(path)
assert s.get(BASE+'/admin',allow_redirects=False).status_code==302
assert s.post(BASE+'/admin/products/save',data={},allow_redirects=False).status_code in [302,403]
post('/admin/login',{'email':'admin@superking.local','password':os.environ['SK_ADMIN_PASSWORD']},'/admin/login')
assert '/admin'==s.get(BASE+'/admin').url.removeprefix(BASE)
r=s.post(BASE+'/admin/categories/save',data={'name':'FORGED'})
assert r.status_code==403,(r.status_code,r.url,r.text[:700])
for path in ['/admin','/admin/products','/admin/categories','/admin/slides','/admin/company_sections','/admin/contacts','/admin/settings','/admin/account']:
    get(path)
post('/admin/categories/save',{'name':'QA temporary category','description':'Temporary integration test','sort_order':99},'/admin/categories/create')
r=get('/admin/categories?q=QA+temporary+category')
category=int(re.search(r'/admin/categories/(\d+)/edit',r.text).group(1))
image=io.BytesIO(); Image.new('RGB',(80,80),'red').save(image,'PNG')
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF'
data={'name':'QA temporary lubricant','category_id':category,'summary':'QA searchable application','benefits':'<p><b>Bold benefit</b></p><ul><li>List item</li></ul><script>alert(1)</script><img src=x onerror=alert(1)>','applications':'<p>Machinery</p>','published':1,'featured':1}
r=post('/admin/products/save',data,'/admin/products/create',{'image':('qa.png',image.getvalue(),'image/png'),'data_sheet':('qa.pdf',pdf,'application/pdf'),'safety_sheet':('qa-safety.pdf',pdf,'application/pdf')})
r=get('/admin/products?q=QA+temporary+lubricant'); product=int(re.search(r'/admin/products/(\d+)/edit',r.text).group(1))
r=get(f'/products/{product}')
assert '<b>Bold benefit</b>' in r.text and '<li>List item</li>' in r.text
assert 'alert(1)' not in r.text and 'onerror' not in r.text
uploaded=re.findall(r'(?:src|href)="[^"]*/(uploads/[a-f0-9]+\.(?:png|pdf))"',r.text)
assert len(uploaded)==3,uploaded
for path in uploaded: assert get('/'+path).content
assert 'QA temporary lubricant' in get(f'/products?q=QA+searchable&category={category}').text
assert 'QA temporary lubricant' in get(f'/products?q=Machinery&category={category}').text
r=post(f'/admin/categories/{category}/delete',{},'/admin/categories'); assert 'Move or delete' in r.text
data.update(id=product,name='QA edited lubricant',published=0,remove_safety_sheet=1)
# Unchecked checkboxes are absent from actual form submission.
data.pop('published')
post('/admin/products/save',data,f'/admin/products/{product}/edit')
assert s.get(BASE+f'/products/{product}').status_code==404
assert s.get(BASE+'/'+uploaded[2]).status_code==404
assert 'QA edited lubricant' in get('/admin/products?q=QA+edited').text
# Replacing the image removes the old file while retaining the product record.
previous_image=uploaded[0]
post('/admin/products/save',data,f'/admin/products/{product}/edit',{'image':('replacement.png',image.getvalue(),'image/png')})
assert s.get(BASE+'/'+previous_image).status_code==404
r=get(f'/admin/products/{product}/edit')
uploaded[0]=re.search(r'src="[^"]*/(uploads/[a-f0-9]+\.png)"',r.text).group(1)
# Invalid upload is rejected and does not replace an existing image.
bad=dict(data)
bad['_token']=token(f'/admin/products/{product}/edit')
r=s.post(BASE+'/admin/products/save',data=bad,files={'image':('fake.png',b'<?php echo 1; ?>','image/png')})
assert 'Please upload a JPG' in r.text and 'QA edited lubricant' in r.text
assert get('/'+uploaded[0]).status_code==200
post(f'/admin/products/{product}/delete',{},'/admin/products')
for file in uploaded: assert s.get(BASE+'/'+file).status_code==404
post(f'/admin/categories/{category}/delete',{},'/admin/categories')
for kind,record in {
    'slides':{'title':'QA temporary slide','description':'Temporary','button_text':'Browse','button_url':'/products'},
    'company_sections':{'title':'QA temporary company section','description':'<p><strong>Test story</strong></p>'},
    'contacts':{'title':'QA temporary contact','email':'qa@example.com','phone':'+95 123456','address':'QA address','hours':'9–5'}
}.items():
    record.update(sort_order=99,published=1)
    post(f'/admin/{kind}/save',record,f'/admin/{kind}/create')
    r=get(f'/admin/{kind}?q=QA+temporary'); rid=int(re.search(fr'/admin/{kind}/(\d+)/edit',r.text).group(1))
    get(f'/admin/{kind}/{rid}/edit')
    record.update(id=rid,title='QA updated '+kind)
    post(f'/admin/{kind}/save',record,f'/admin/{kind}/{rid}/edit')
    assert 'QA updated' in get(f'/admin/{kind}?q=QA+updated').text
    post(f'/admin/{kind}/{rid}/delete',{},f'/admin/{kind}')
# Brand updates propagate through the public and admin shells, including uploads.
r=get('/admin/settings')
def input_value(name):
    return re.search(fr'id="{name}"[^>]*value="([^"]*)"',r.text).group(1)
from html import unescape
original={'company_name':unescape(input_value('company_name')),'tagline':unescape(input_value('tagline')),'theme_color':input_value('theme_color'),'intro':unescape(re.search(r'<textarea id="intro"[^>]*>(.*?)</textarea>',r.text,re.S).group(1))}
if 'name="carousel_autoplay" checked' in r.text: original['carousel_autoplay']=1
updated=dict(original,company_name='QA Brand Company',theme_color='#087f74')
post('/admin/settings',updated,'/admin/settings',{'logo':('logo.png',image.getvalue(),'image/png'),'favicon':('icon.png',image.getvalue(),'image/png')})
r=get('/')
assert 'QA Brand Company' in r.text and '--brand:#087f74' in r.text
brand_files=re.findall(r'(?:src|href)="[^"]*/(uploads/[a-f0-9]+\.png)"',r.text)
assert len(brand_files)==2
assert 'QA Brand Company' in get('/admin').text
original.update(remove_logo=1,remove_favicon=1)
post('/admin/settings',original,'/admin/settings')
for file in brand_files: assert s.get(BASE+'/'+file).status_code==404
assert original['company_name'] in get('/').text
# Account password changes, logout, old-password rejection and restoration.
initial=os.environ['SK_ADMIN_PASSWORD']
temporary=os.urandom(12).hex()
post('/admin/account',{'email':'admin@superking.local','current_password':initial,'new_password':temporary,'confirm_password':temporary},'/admin/account')
post('/admin/logout',{},'/admin')
assert s.get(BASE+'/admin',allow_redirects=False).status_code==302
r=s.post(BASE+'/admin/login',data={'_token':token('/admin/login'),'email':'admin@superking.local','password':initial})
assert 'Email or password is incorrect' in r.text
post('/admin/login',{'email':'admin@superking.local','password':temporary},'/admin/login')
post('/admin/account',{'email':'admin@superking.local','current_password':temporary,'new_password':initial,'confirm_password':initial},'/admin/account')
post('/admin/logout',{},'/admin')
print('PASS: auth/account/password/logout, CSRF, all CRUD systems, search/category filtering, drafts, rich text sanitization, image/PDF uploads, invalid uploads, file replacement/removal, dynamic company name/color/logo/favicon and cleanup.')
