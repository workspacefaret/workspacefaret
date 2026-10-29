"""Prueba TLS del proxy api.php: login + GET catalogos y compara con el resultado esperado.

    python -I tests/extintores/php_tls_test.py <puerto_web> <200|502>

Entorno preparado con php_gate_setup.ps1 -Https [-ConCa] [-HostApi localhost] + php_gate_seed.php. Solo local.
"""
import http.client
import sys
import urllib.parse

PW, ESPERADO = int(sys.argv[1]), int(sys.argv[2])
c = http.client.HTTPConnection('127.0.0.1', PW, timeout=30)
c.request('GET', '/auth/login.php')
r = c.getresponse(); r.read()
ck = [v.split(';')[0] for k, v in r.getheaders() if k.lower() == 'set-cookie'][0]
c = http.client.HTTPConnection('127.0.0.1', PW, timeout=30)
c.request('POST', '/auth/login.php', urllib.parse.urlencode({'username': 't_ver', 'password': 'Local1234!'}),
          {'Content-Type': 'application/x-www-form-urlencoded', 'Cookie': ck})
r = c.getresponse(); r.read()
ck = [v.split(';')[0] for k, v in r.getheaders() if k.lower() == 'set-cookie'][0]
c = http.client.HTTPConnection('127.0.0.1', PW, timeout=60)
c.request('GET', '/modules/prevencion/extintores/api.php?r=catalogos', headers={'Cookie': ck})
r = c.getresponse(); cuerpo = r.read().decode('utf-8', 'replace')
ok = r.status == ESPERADO and (ESPERADO != 200 or 'criterios' in cuerpo) and (ESPERADO != 502 or 'comunicar' in cuerpo)
print(('OK    ' if ok else 'FALLA ') + f'estado={r.status} esperado={ESPERADO} cuerpo={cuerpo[:80]!r}')
sys.exit(0 if ok else 1)
