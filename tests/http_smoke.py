#!/usr/bin/env python3
"""HTTP checks against a running disposable Compose environment (stdlib only)."""
import html
import http.cookiejar
import os
import re
import urllib.error
import urllib.request

BASE = os.environ.get('TEST_BASE_URL', 'http://127.0.0.1:8080')

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        return None

opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def request(path, data=None, headers=None):
    req = urllib.request.Request(BASE + path, data=data, headers=headers or {})
    try:
        response = opener.open(req, timeout=15)
    except urllib.error.HTTPError as error:
        response = error
    return response.code, response.read().decode('utf-8'), response.headers

for path in ['/', '/parents.php', '/parents.php?pr=1&t=1', '/go.php', '/teachers_access.php']:
    status, body, headers = request(path)
    assert status == 200, (path, status, body[:500])
    assert 'Critical Error' not in body and 'Stack Trace' not in body, path
    assert headers.get('X-Content-Type-Options') == 'nosniff', path
    for asset in set(re.findall(r'(?:src|href)="(/atk/[^"?#]+)', body)):
        assert request(html.unescape(asset))[0] == 200, asset
    print('PASS page and assets:', path)

for path in ['/parentslist.php?id=1', '/print.php?id=1', '/admin.php', '/teachers.php', '/cons.php?id=1', '/open.php?id=1']:
    assert request(path)[0] == 302, path
    print('PASS authentication required:', path)

for path in ['/docs/db.sql', '/terraform/main.tf', '/.git/config', '/vendor/autoload.php', '/composer.json', '/bootstrap.php', '/database/schema.sql']:
    assert request(path)[0] in (403, 404), path
    print('PASS private file blocked:', path)

assert request('/go.php', data=b'password=invalid')[0] == 403
assert request('/admin.php?__atk_cb_test=ajax')[0] == 403
print('PASS CSRF required for POST and GET callbacks')
