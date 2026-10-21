"""Test migrate:fresh using only a uniquely named disposable MySQL database."""
import json
import os
from pathlib import Path
import re
import subprocess
import time

ROOT = Path(__file__).resolve().parents[1]
DATABASE = 'superking_fresh_qa_' + str(time.time_ns())
assert re.fullmatch(r'superking_fresh_qa_\d+', DATABASE)
ENV = dict(os.environ, DB_DATABASE=DATABASE, SEED_PROFILE='minimal', SEED_ADMIN_PASSWORD='Migration-admin-password-2026')


def console(*args, input_text='', expected=0, environment=None):
    result = subprocess.run(['php', 'bin/console', *args], cwd=ROOT,
                            env=environment or ENV, input=input_text,
                            capture_output=True, text=True, encoding='utf-8', timeout=30)
    # Do not print successful seed output: it includes a generated administrator password.
    assert result.returncode == expected, (args, result.returncode, result.stderr)
    return result


def sql(query):
    script = "require 'bootstrap/app.php'; $query=json_decode(stream_get_contents(STDIN),true,512,JSON_THROW_ON_ERROR); $result=Mini\\Database::statement($query); echo json_encode($result->columnCount() ? $result->fetchAll() : []);"
    result = subprocess.run(['php', '-r', script], cwd=ROOT, env=ENV,
                            input=json.dumps(query), capture_output=True, text=True,
                            encoding='utf-8', timeout=15)
    assert result.returncode == 0, result.stderr
    return json.loads(result.stdout)


def tables():
    return {row['TABLE_NAME'] for row in sql('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()')}


try:
    assert 'migrate:fresh [--seed] [--minimal] [--force]' in console('help').stdout
    for invalid_name in ['bad-name', 'mysql', 'information_schema', 'performance_schema', 'sys']:
        rejected = console('migrate:fresh', '--force', environment=dict(ENV, DB_DATABASE=invalid_name), expected=1)
        assert 'ERROR:' in rejected.stderr

    # A missing database can be initialized without invoking forward-only down migrations.
    fresh = console('migrate:fresh', '--force')
    assert 'Dropped 0 database object(s)' in fresh.stdout
    names = sorted(path.name for path in (ROOT / 'migrations').glob('*.php'))
    assert [row['migration'] for row in sql('SELECT migration FROM migrations ORDER BY migration')] == names
    assert sql('SELECT COUNT(*) count FROM admins')[0]['count'] == 0
    assert sql('SELECT COUNT(*) count FROM settings')[0]['count'] == 0
    assert 'internal_notes' in {row['Field'] for row in sql('SHOW COLUMNS FROM inquiries')}
    assert 'Database is up to date' in console('migrate').stdout
    assert 'forward-only' in console('migrate:rollback', expected=1).stderr

    seeded = console('db:seed')
    assert 'Initial password:' in seeded.stdout
    original_hash = sql('SELECT password FROM admins')[0]['password']
    sql('CREATE TABLE qa_parent (id INT PRIMARY KEY) ENGINE=InnoDB')
    sql('CREATE TABLE qa_child (id INT PRIMARY KEY, parent_id INT, FOREIGN KEY (parent_id) REFERENCES qa_parent(id)) ENGINE=InnoDB')
    sql('INSERT INTO qa_parent VALUES (1)')
    sql('INSERT INTO qa_child VALUES (1,1)')
    sql('CREATE VIEW qa_view AS SELECT id FROM qa_parent')
    sql('CREATE TABLE `qa``quoted` (id INT PRIMARY KEY)')
    original_tables = tables()

    # A typo, EOF or declined confirmation must leave every existing table and row intact.
    for args, answer in [(('migrate:fresh', '--sead'), ''), (('migrate:fresh',), ''), (('migrate:fresh', '--seed'), 'no\n'), (('migrate:fresh',), 'yes\n')]:
        console(*args, input_text=answer, expected=1)
        assert tables() == original_tables
        assert sql('SELECT * FROM qa_child') == [{'id': 1, 'parent_id': 1}]
        assert sql('SELECT password FROM admins')[0]['password'] == original_hash

    # Confirming the exact database name performs the reset, including untracked FK tables/views.
    confirmed = console('migrate:fresh', input_text=DATABASE + '\n')
    assert DATABASE in confirmed.stdout and 'Fresh migration complete' in confirmed.stdout
    assert not {'qa_parent', 'qa_child', 'qa_view', 'qa`quoted'} & tables()
    assert sql('SELECT COUNT(*) count FROM admins')[0]['count'] == 0
    assert sql('SELECT COUNT(*) count FROM products')[0]['count'] == 0
    assert sql('SELECT COUNT(*) count FROM migrations')[0]['count'] == len(names)
    assert {row['batch'] for row in sql('SELECT batch FROM migrations')} == {1}

    # --seed rebuilds the company settings, categories and a fresh administrator.
    seeded_fresh = console('migrate:fresh', '--seed', '--force')
    assert 'Initial password:' in seeded_fresh.stdout
    assert sql('SELECT COUNT(*) count FROM admins')[0]['count'] == 1
    assert sql('SELECT COUNT(*) count FROM settings')[0]['count'] == 1
    assert sql('SELECT COUNT(*) count FROM categories')[0]['count'] == 5
    assert sql('SELECT COUNT(*) count FROM products')[0]['count'] == 0
    assert sql('SELECT password FROM admins')[0]['password'] != original_hash
    assert 'Database is up to date' in console('migrate').stdout
    status = console('migrate:status').stdout
    assert all(name in status for name in names)
    new_hash = sql('SELECT password FROM admins')[0]['password']
    console('db:seed')
    assert sql('SELECT password FROM admins')[0]['password'] == new_hash
    assert sql('SELECT COUNT(*) count FROM categories')[0]['count'] == 5
    print('PASS: fresh database creation, exact-name confirmation, cancellation, option/system-schema guards, FK tables/views, quoted identifiers, migration replay and optional seeding.')
finally:
    cleanup = "require 'bootstrap/app.php'; $qaName=getenv('DB_DATABASE'); if(!preg_match('/^superking_fresh_qa_[0-9]+$/',$qaName))exit(1); Mini\\Database::connection(true)->exec('DROP DATABASE IF EXISTS `'.$qaName.'`');"
    result = subprocess.run(['php', '-r', cleanup], cwd=ROOT, env=ENV,
                            capture_output=True, text=True, timeout=15)
    assert result.returncode == 0, 'Unable to remove disposable test database'
