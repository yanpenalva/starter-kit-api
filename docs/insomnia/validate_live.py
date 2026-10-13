#!/usr/bin/env python3
"""Run the collection's operations over loopback HTTP against disposable SQLite."""
import base64
import hashlib
import hmac
import http.cookiejar
import json
import os
from pathlib import Path
import re
import secrets
import socket
import sqlite3
import struct
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
PHP = '/opt/data/php/root/usr/bin/php8.5'
OUTPUT = ROOT / 'docs/insomnia/live-responses.json'
ROUTER = r'''<?php
require getenv('LIVE_ROOT').'/vendor/autoload.php';
$app = require getenv('LIVE_ROOT').'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
Illuminate\Support\Facades\Notification::fake();
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$captured = [];
foreach (Illuminate\Support\Facades\Notification::sentNotifications() as $byId) {
    foreach ($byId as $id => $byClass) {
        foreach ($byClass as $class => $entries) {
            foreach ($entries as $entry) {
                $user = App\Models\User::findOrFail($id);
                $captured[] = ['class' => $class, 'url' => $entry['notification']->toMail($user)->actionUrl];
            }
        }
    }
}
if ($captured !== []) {
    file_put_contents(getenv('LIVE_NOTIFICATIONS'), json_encode($captured));
}
$response->send();
$kernel->terminate($request, $response);
'''
FIXTURE = r'''<?php
require getenv('LIVE_ROOT').'/vendor/autoload.php';
$app = require getenv('LIVE_ROOT').'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
assert(config('database.default') === 'sqlite');
$user = App\Models\User::create(['name'=>'Live Admin','email'=>'admin@example.test',
'password'=>getenv('LIVE_PASSWORD'),'email_verified_at'=>now(),'deactivated_at'=>null]);
$user->assignRole('admin');
'''


def redact(value, key=''):
    if key in {'secret', 'provisioning_uri', 'cpf', 'token', 'password', 'signature', 'hash'}:
        return '[redacted]'
    if key == 'recovery_codes':
        return ['[redacted]' for _ in value]
    if isinstance(value, dict):
        return {k: redact(v, k) for k, v in value.items()}
    if isinstance(value, list):
        return [redact(v, key) for v in value]
    if key == 'id' and isinstance(value, str) and len(value) >= 30:
        return '[session-id-redacted]'
    return value


def totp(secret, offset=0, timestamp=None):
    counter = int((time.time() if timestamp is None else timestamp) // 30) + offset
    raw = hmac.new(base64.b32decode(secret), struct.pack('>Q', counter), hashlib.sha1).digest()
    pos = raw[-1] & 15
    return f'{(struct.unpack(">I", raw[pos:pos+4])[0] & 0x7fffffff) % 1000000:06d}'


class Client:
    def __init__(self, base, records):
        self.base, self.records = base, records
        self.jar = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.ProxyHandler({}), urllib.request.HTTPCookieProcessor(self.jar))

    def call(self, method, path, body=None, status=200, route=None, label='', csrf=True):
        headers = {'Accept': 'application/json', 'Origin': self.base, 'Referer': self.base+'/',
                   'User-Agent': 'Insomnia-live-validation'}
        if csrf:
            for cookie in self.jar:
                if cookie.name == 'XSRF-TOKEN':
                    headers['X-XSRF-TOKEN'] = urllib.parse.unquote(cookie.value)
        data = None if body is None else json.dumps(body).encode()
        if data is not None:
            headers['Content-Type'] = 'application/json'
        request = urllib.request.Request(self.base+path, data=data, headers=headers, method=method)
        try:
            response = self.opener.open(request, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        raw = response.read().decode()
        try:
            result = json.loads(raw) if raw else None
        except json.JSONDecodeError:
            result = raw
        safe_path = path.split('?')[0]
        safe_path = re.sub(r'(/email/verify/\d+/)[^/]+', r'\1[redacted]', safe_path)
        safe_path = re.sub(r'(/auth/sessions/)[^/]+', r'\1[session-id-redacted]', safe_path)
        self.records.append({'method': method, 'route': route or safe_path, 'path': safe_path,
                             'status': response.code, 'content_type': response.headers.get('Content-Type'),
                             'body': redact(result), 'label': label})
        OUTPUT.write_text(json.dumps(self.records, indent=2, ensure_ascii=False)+'\n')
        print(method, route or safe_path, response.code, label, flush=True)
        assert response.code == status, f'{method} {safe_path}: expected {status}, got {response.code}: {redact(result)}'
        return result

    def csrf(self):
        self.call('GET', '/sanctum/csrf-cookie', status=204)

    def login(self, email, password, status=204):
        self.csrf()
        return self.call('POST', '/api/v1/login', {'email': email, 'password': password}, status)


def run():
    # Small self-checks protect the independent TOTP generator and sanitizer.
    assert totp('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', timestamp=59) == '287082'
    assert redact({'secret': 'x', 'recovery_codes': ['x'], 'nested': {'cpf': 'x'}}) == {
        'secret': '[redacted]', 'recovery_codes': ['[redacted]'], 'nested': {'cpf': '[redacted]'}}
    records = []
    with tempfile.TemporaryDirectory(prefix='insomnia-live-', dir=ROOT/'storage/app') as directory:
        private = Path(directory)
        with sqlite3.connect(private/'database.sqlite') as db:
            db.execute('CREATE TABLE cache (key TEXT PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER NOT NULL)')
            db.execute('CREATE TABLE cache_locks (key TEXT PRIMARY KEY, owner TEXT NOT NULL, expiration INTEGER NOT NULL)')
        (private/'router.php').write_text(ROUTER)
        (private/'fixture.php').write_text(FIXTURE)
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            port = sock.getsockname()[1]
        with socket.socket() as sock:
            sock.bind(('127.0.0.1', 0))
            redis_port = sock.getsockname()[1]
        base = f'http://127.0.0.1:{port}'
        password, new_password = secrets.token_urlsafe(24), secrets.token_urlsafe(24)
        env = os.environ.copy()
        env.update(APP_ENV='testing', APP_DEBUG='false', APP_KEY='base64:'+base64.b64encode(secrets.token_bytes(32)).decode(),
                   APP_URL=base, FRONTEND_URL=base, DB_CONNECTION='sqlite', DB_DATABASE=str(private/'database.sqlite'),
                   DB_URL='', DB_CACHE_CONNECTION='sqlite', DB_CACHE_LOCK_CONNECTION='sqlite',
                   SESSION_CONNECTION='sqlite', SESSION_DRIVER='database', SESSION_DOMAIN='', SESSION_SECURE_COOKIE='false',
                   SESSION_COOKIE='insomnia_live_session', CACHE_STORE='database', QUEUE_CONNECTION='sync', MAIL_MAILER='log',
                   SANCTUM_STATEFUL_DOMAINS=f'127.0.0.1:{port}', REDIS_HOST='127.0.0.1', REDIS_PORT=str(redis_port),
                   REDIS_URL='', REDIS_CLIENT='phpredis', LOG_CHANNEL='single', LOG_LEVEL='error',
                   APP_CONFIG_CACHE=str(private/'config.php'), APP_ROUTES_CACHE=str(private/'routes.php'),
                   LIVE_ROOT=str(ROOT), LIVE_NOTIFICATIONS=str(private/'notifications.json'), LIVE_PASSWORD=password)
        # Redirect even unexpected error logs away from the repository's regular logs.
        env['LOG_CHANNEL'] = 'stderr'
        def php(*args):
            result = subprocess.run([PHP, *args], cwd=ROOT, env=env, capture_output=True, text=True)
            if result.returncode:
                raise RuntimeError(result.stdout + result.stderr)
            return result
        env['CACHE_STORE'] = 'array'
        php('artisan', 'migrate', '--force')
        env['CACHE_STORE'] = 'database'
        php('artisan', 'db:seed', '--force')
        php(str(private/'fixture.php'))
        routes = json.loads(php('artisan', 'route:list', '--json').stdout)
        (ROOT/'docs/insomnia/live-routes.json').write_text(json.dumps([
            {'method': r['method'], 'uri': r['uri']} for r in routes], indent=2)+'\n')
        log = open(private/'server.log', 'w')
        server = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', str(private/'router.php')],
                                  cwd=ROOT, env=env, stdout=log, stderr=log)
        try:
            for attempt in range(100):
                try:
                    with socket.create_connection(('127.0.0.1', port), timeout=.1):
                        break
                except OSError:
                    time.sleep(.05)
            c = Client(base, records)
            c.call('GET', '/api/v1')
            c.call('GET', '/api/v1/health', status=503, label='Redis intentionally absent; no real service')
            c.call('GET', '/up')
            c.csrf()
            c.call('POST', '/api/v1/login', {'email':'admin@example.test','password':password}, status=419,
                   label='negative: CSRF header absent', csrf=False)
            c.call('POST', '/api/v1/register', {'name':'Dev Exemplo','email':'dev@example.test',
                   'password':password,'password_confirmation':password}, 201)
            c.login('dev@example.test', password)
            c.call('GET', '/api/v1/auth/my-profile', label='unverified profile')
            c.call('POST', '/api/v1/email/verification-notification', status=202)
            notifications = json.loads((private/'notifications.json').read_text())
            query = urllib.parse.parse_qs(urllib.parse.urlsplit(notifications[-1]['url']).query)
            verify = '/api/v1/email/verify/'+query.pop('id')[0]+'/'+query.pop('hash')[0]
            verify += '?'+urllib.parse.urlencode({k:v[0] for k,v in query.items()})
            c.call('GET', verify, route='/api/v1/email/verify/{id}/{hash}')
            c.call('GET', '/api/v1/auth/my-profile', label='verified profile')
            c.call('PATCH', '/api/v1/auth/my-profile', {'name':'Dev Atualizado'})
            c.call('PUT', '/api/v1/auth/password', {'current_password':password,'password':new_password,
                   'password_confirmation':new_password})
            second = Client(base, records)
            second.login('dev@example.test', new_password)
            sessions = c.call('GET', '/api/v1/auth/sessions')['data']
            other_id = next(s['id'] for s in sessions if not s['is_current'])
            c.call('DELETE', '/api/v1/auth/sessions/'+other_id, status=204, route='/api/v1/auth/sessions/{id}')
            second.call('GET', '/api/v1/auth/my-profile', status=401, label='negative: revoked session')
            second.login('dev@example.test', new_password)
            c.call('DELETE', '/api/v1/auth/sessions', status=204)
            second.call('GET', '/api/v1/auth/my-profile', status=401, label='negative: destroyOthers revoked session')
            c.call('POST', '/api/v1/forgot-password', {'email':'dev@example.test'}, 202)
            notifications = json.loads((private/'notifications.json').read_text())
            query = urllib.parse.parse_qs(urllib.parse.urlsplit(notifications[-1]['url']).query)
            c.call('POST', '/api/v1/reset-password', {'email':'dev@example.test','token':query['token'][0],
                   'password':password,'password_confirmation':password})
            c.call('GET', '/api/v1/auth/my-profile', status=401, label='negative: reset invalidated session')
            c.login('admin@example.test', password)
            c.call('GET', '/api/v1/auth/mfa/status')
            enrollment = c.call('POST', '/api/v1/auth/mfa/enroll', {'password':password})
            confirmation_code = totp(enrollment['secret'])
            c.call('POST', '/api/v1/auth/mfa/confirm', {'code':confirmation_code})
            c.login('admin@example.test', password, status=200)
            c.call('POST', '/api/v1/auth/mfa/challenge', {'code':confirmation_code}, 422,
                   label='negative: confirmation TOTP replay')
            c.call('POST', '/api/v1/auth/mfa/challenge', {'code':totp(enrollment['secret'], 1)}, 204,
                   label='real independent TOTP; next window accepted by ±1 tolerance')
            recovery = c.call('POST', '/api/v1/auth/mfa/recovery-codes')['recovery_codes']
            c.call('POST', '/api/v1/logout', status=204)
            c.login('admin@example.test', password, status=200)
            c.call('POST', '/api/v1/auth/mfa/challenge', {'recovery_code':recovery[0]}, 204, label='recovery code')
            c.call('POST', '/api/v1/logout', status=204)
            c.login('admin@example.test', password, status=200)
            c.call('POST', '/api/v1/auth/mfa/challenge', {'recovery_code':recovery[0]}, 422, label='negative: recovery replay')
            c.call('POST', '/api/v1/auth/mfa/challenge', {'recovery_code':recovery[1]}, 204, label='unused recovery code')
            c.call('POST', '/api/v1/auth/mfa/disable', status=204)
            c.call('POST', '/api/v1/users', {'name':'Usuário Manual','email':'manual.user@example.test',
                   'cpf':'52998224725','password':password,'active':True,'roles':['user']}, 201)
            user_id = records[-1]['body']['data']['id']
            u = f'/api/v1/users/{user_id}'
            c.call('GET', '/api/v1/users?page=1&per_page=15', route='/api/v1/users')
            c.call('GET', u, route='/api/v1/users/{user}')
            c.call('PATCH', u, {'name':'Usuário Atualizado','email':'manual.updated@example.test','cpf':'52998224725'},
                   route='/api/v1/users/{user}')
            role = c.call('POST', '/api/v1/roles', {'name':'manual-reviewer'}, 201)['data']['id']
            r = f'/api/v1/roles/{role}'
            c.call('GET', '/api/v1/roles?page=1&per_page=15', route='/api/v1/roles')
            c.call('GET', r, route='/api/v1/roles/{role}')
            c.call('PATCH', r, {'name':'manual-reviewer-renamed'}, route='/api/v1/roles/{role}')
            c.call('PUT', r+'/permissions', {'permissions':['users.view']}, route='/api/v1/roles/{role}/permissions')
            c.call('PUT', u+'/roles', {'roles':['user']}, route='/api/v1/users/{user}/roles')
            c.call('PUT', u+'/roles', {'roles':['user','manual-reviewer-renamed']}, route='/api/v1/users/{user}/roles', label='additional: attach created role')
            c.call('POST', u+'/status', {'status':'deactivated'}, route='/api/v1/users/{user}/status')
            c.call('DELETE', u, status=204, route='/api/v1/users/{user}')
            c.call('DELETE', r, status=204, route='/api/v1/roles/{role}')
            c.call('GET', '/api/v1/activity-log?page=1&per_page=15', route='/api/v1/activity-log')
            c.call('POST', '/api/v1/logout', status=204)
            expected = {(r['method'].split('|')[0], '/'+r['uri'].rstrip('/')) for r in routes if r['uri'].startswith('api/v1')}
            observed = {(r['method'], r['route'].rstrip('/')) for r in records}
            assert len(expected) == 35 and expected <= observed, expected-observed
            print('PASS: 35/35 API operations + CSRF + /up; negative security assertions passed')
        finally:
            server.terminate()
            server.wait(timeout=10)
            log.close()


if __name__ == '__main__':
    run()
