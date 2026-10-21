"""Verify every module's seed data in a disposable database, without touching company data."""
import json
import os
from pathlib import Path
import re
import subprocess
import time

ROOT=Path(__file__).resolve().parents[1]
DATABASE='superking_seed_qa_'+str(time.time_ns())
assert re.fullmatch(r'superking_seed_qa_\d+',DATABASE)
ENV=dict(os.environ,DB_DATABASE=DATABASE,SEED_PROFILE='demo',SEED_ADMIN_PASSWORD='Seeding-test-password-2026')


def console(*args, expected=0, environment=None):
    result=subprocess.run(['php','bin/console',*args],cwd=ROOT,env=environment or ENV,input='',capture_output=True,text=True,encoding='utf-8',timeout=30)
    assert result.returncode==expected,(args,result.stderr)
    return result


def sql(query):
    script="require 'bootstrap/app.php'; $query=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); $result=Mini\\Database::statement($query); echo json_encode($result->columnCount() ? $result->fetchAll() : []);"
    result=subprocess.run(['php','-r',script],cwd=ROOT,env=ENV,input=json.dumps(query),capture_output=True,text=True,encoding='utf-8',timeout=15)
    assert result.returncode==0,result.stderr
    return json.loads(result.stdout)


EXPECTED={'settings':1,'admins':1,'categories':5,'products':15,'slides':2,'company_sections':3,'contacts':3,'articles':9,'manufacturing_updates':3,'dealers':6,'documents':4,'inquiries':8,'admin_activity':9}


def snapshot():
    return {table:sql('SELECT * FROM '+table+' ORDER BY id') for table in EXPECTED}


try:
    result=console('migrate:fresh','--seed','--force')
    assert 'Seed profile: demo' in result.stdout and 'Initial password:' in result.stdout
    for table,count in EXPECTED.items():
        assert sql('SELECT COUNT(*) count FROM '+table)[0]['count']==count,table
    assert sql('SELECT COUNT(*) count FROM migrations')[0]['count']==len(list((ROOT/'migrations').glob('*.php')))
    for table in ['login_attempts','inquiry_limits']:
        assert sql('SELECT COUNT(*) count FROM '+table)[0]['count']==0,table
    for table in ['products','slides','contacts','articles','manufacturing_updates','dealers','documents']:
        assert sql('SELECT COUNT(*) count FROM '+table+' WHERE published=1')[0]['count']==0,table
    for table in ['manufacturing_updates','dealers','documents']:
        assert sql('SELECT COUNT(*) count FROM '+table+' WHERE verified=1')[0]['count']==0,table
    assert sql('SELECT COUNT(*) count FROM company_sections WHERE published=1')[0]['count']==1
    assert sql('SELECT COUNT(*) count FROM products p JOIN categories c ON c.id=p.category_id')[0]['count']==15
    assert sql("SELECT COUNT(*) count FROM articles a JOIN products p ON p.id=a.related_product_id WHERE a.kind='knowledge'")[0]['count']==7
    assert {row['kind'] for row in sql('SELECT DISTINCT kind FROM inquiries')}=={'product','distributor','complaint','general'}
    assert {row['status'] for row in sql('SELECT DISTINCT status FROM inquiries')}=={'new','in_progress','resolved'}
    assert sql("SELECT COUNT(*) count FROM inquiries i JOIN admins a ON a.id=i.handled_by WHERE i.status<>'new'")[0]['count']==5
    assert sql('SELECT COUNT(*) count FROM products WHERE name_my IS NOT NULL AND summary_my IS NOT NULL')[0]['count']==15
    assert all(row['email'].endswith('@example.invalid') for row in sql('SELECT email FROM contacts'))
    assert all(row['email'].endswith('@example.invalid') for row in sql('SELECT email FROM inquiries'))
    for document in sql('SELECT file FROM documents'):
        file=(ROOT/'public'/document['file']).resolve()
        assert file.is_relative_to((ROOT/'public/assets/documents/demo').resolve())
        content=file.read_bytes()
        assert content.startswith(b'%PDF-') and b'NOT APPROVED FOR PUBLICATION' in content and b'%%EOF' in content
    first=snapshot()
    assert 'Initial password:' not in console('db:seed').stdout
    assert first==snapshot(),'Repeated seeding changed or duplicated records'

    # Staff edits, approvals, company settings, notes and credentials survive reruns.
    sql("UPDATE products SET name='Staff renamed product',summary='Staff description',published=1 WHERE seed_key='demo.product.motorcycle.1'")
    sql("UPDATE categories SET name='Staff renamed category' WHERE seed_key='category.motorcycle'")
    sql("UPDATE articles SET title='Staff renamed article',description='<p>Staff content</p>' WHERE seed_key='demo.knowledge.fundamentals'")
    sql("UPDATE settings SET intro='Staff company introduction',ga4_id='G-TEST0001' WHERE id=1")
    sql("UPDATE admins SET email='staff-changed@example.invalid' WHERE id=1")
    sql("UPDATE inquiries SET status='resolved',internal_notes='Staff notes' WHERE seed_key='demo.inquiry.001'")
    edited=snapshot()
    console('db:seed',environment=dict(ENV,SEED_ADMIN_PASSWORD='Different-seed-password-2026'))
    assert edited==snapshot(),'Seeding overwrote staff changes or credentials'
    console('db:seed','--minimal')
    assert edited==snapshot(),'Minimal profile changed existing demo content'

    # Restore only a deleted seed record; retain the other banner and every existing ID.
    remaining=sql("SELECT * FROM slides WHERE seed_key='demo.banner.distributor'")[0]
    sql("DELETE FROM slides WHERE seed_key='demo.banner.products'")
    console('db:seed')
    assert sql('SELECT COUNT(*) count FROM slides')[0]['count']==2
    assert sql("SELECT * FROM slides WHERE seed_key='demo.banner.distributor'")[0]==remaining
    for args in [('db:seed','--invalid'),('migrate:fresh','--minimal','--force')]:
        before=snapshot();console(*args,expected=1);assert before==snapshot()
    bad_env=dict(ENV,SEED_PROFILE='invalid')
    before=snapshot();console('migrate:fresh','--seed','--force',environment=bad_env,expected=1);assert before==snapshot()

    # Initial setup can deliberately omit all demonstration modules.
    console('migrate:fresh','--seed','--minimal','--force')
    for table in EXPECTED:
        expected={'settings':1,'admins':1,'categories':5}.get(table,0)
        assert sql('SELECT COUNT(*) count FROM '+table)[0]['count']==expected,table
    before=sql('SELECT * FROM admins')
    console('db:seed')
    assert before==sql('SELECT * FROM admins')
    for table,count in EXPECTED.items():
        assert sql('SELECT COUNT(*) count FROM '+table)[0]['count']==count,table
    print('PASS: all business modules, linked records, bilingual drafts, demo PDFs, inquiry states, runtime-table exclusions, idempotence, preserved edits/credentials, deleted-record recovery and minimal/full profiles.')
    # Explicit publication touches content only and never fabricates approval.
    content_tables=['products','slides','company_sections','contacts','articles','manufacturing_updates','dealers','documents']
    private_before={table:sql('SELECT * FROM '+table+' ORDER BY id') for table in ['admins','inquiries','admin_activity','settings']}
    sql("UPDATE products SET image='uploads/staff-photo.webp' WHERE seed_key='demo.product.motorcycle.1'")
    console('content:publish-demo')
    for table in content_tables:
        assert sql("SELECT COUNT(*) count FROM "+table+" WHERE LEFT(seed_key,5)='demo.' AND published=0")[0]['count']==0,table
    for table in ['dealers','documents','manufacturing_updates']:
        assert sql('SELECT COUNT(*) count FROM '+table+' WHERE verified=1')[0]['count']==0
    assert sql("SELECT COUNT(*) count FROM articles WHERE kind='knowledge' AND reviewer<>''")[0]['count']==0
    assert sql("SELECT image FROM products WHERE seed_key='demo.product.motorcycle.1'")[0]['image']=='uploads/staff-photo.webp'
    assert private_before=={table:sql('SELECT * FROM '+table+' ORDER BY id') for table in private_before}
    published=snapshot(); console('content:publish-demo'); assert published==snapshot()
    console('db:seed'); assert published==snapshot(),'Reseeding changed publication choices'
    console('content:publish-demo','--invalid',expected=1); assert published==snapshot()
    print('PASS: explicit demo publication, private data preservation, retained photos and honest verification state.')

finally:
    cleanup="require 'bootstrap/app.php'; $qaName=getenv('DB_DATABASE'); if(!preg_match('/^superking_seed_qa_[0-9]+$/',$qaName))exit(1); Mini\\Database::connection(true)->exec('DROP DATABASE IF EXISTS `'.$qaName.'`');"
    result=subprocess.run(['php','-r',cleanup],cwd=ROOT,env=ENV,capture_output=True,text=True,timeout=15)
    assert result.returncode==0,'Unable to remove disposable seed test database'
