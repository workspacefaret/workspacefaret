"""Prueba de la puerta administrativa modules/prevencion/extintores/api.php (PHP + sesion) contra una API LOCAL.

    powershell -File tests/extintores/php_gate_setup.ps1 -Conn "<cadena_sqlserver_local>"   (prepara docroot, API y php -S)
    python -I tests/extintores/php_gate_test.py <puerto_web>

Verifica sesion, niveles, CSRF, lista blanca de rutas y que no haya claves en HTML/JS. NUNCA toca produccion.
"""
import http.client
import http.cookiejar
import json
import os
import shutil
import socket
import subprocess
import sys
import tempfile
import time
import urllib.parse

sys.stdout.reconfigure(encoding='utf-8', errors='replace')
PW = int(sys.argv[1])        # puerto de php -S (docroot de prueba ya preparado por php_gate_setup.ps1)
KEY = 'clave-firma-local-de-prueba'
RES = []
SUF = str(int(time.time()))   # nombres unicos: la prueba se puede repetir sobre la misma BD


def T(n, ok, d=''):
    RES.append(bool(ok))
    print(('  OK    ' if ok else '  FALLA ') + n + (f'  [{d}]' if d and not ok else ''))


try:
    HOST = f'127.0.0.1:{PW}'

    class Sesion:
        def __init__(self):
            self.cookie = ''

        def req(self, metodo, ruta, cuerpo=None, headers=None, form=None):
            c = http.client.HTTPConnection('127.0.0.1', PW, timeout=30)
            h = dict(headers or {})
            if self.cookie:
                h['Cookie'] = self.cookie
            data = None
            if form is not None:
                data = urllib.parse.urlencode(form)
                h['Content-Type'] = 'application/x-www-form-urlencoded'
            elif cuerpo is not None:
                data = json.dumps(cuerpo)
                h['Content-Type'] = 'application/json'
            c.request(metodo, ruta, body=data, headers=h)
            resp = c.getresponse()
            body = resp.read().decode('utf-8', 'replace')
            for k, v in resp.getheaders():
                if k.lower() == 'set-cookie':
                    self.cookie = v.split(';')[0]
            return resp.status, body

        def login(self, u):
            self.req('GET', '/auth/login.php')
            return self.req('POST', '/auth/login.php', form={'username': u, 'password': 'Local1234!'})

    GW = '/modules/prevencion/extintores/api.php'
    ORIG = {'Origin': f'http://{HOST}'}

    print('== Sin sesión')
    anon = Sesion()
    for m, r in [('GET', 'catalogos'), ('GET', 'areas'), ('GET', 'brigadistas'), ('GET', 'consolidado'), ('GET', '')]:
        s, _ = anon.req(m, f'{GW}?r={r}')
        T(f'anónimo {m} {r or "/"} → 401', s == 401, s)
    s, _ = anon.req('POST', f'{GW}?r=areas', {'nombre': 'x'}, ORIG)
    T('anónimo POST areas → 401', s == 401, s)

    print('== Usuarios sin permiso')
    for u, desc in [('t_none', 'sin módulo'), ('t_otro', 'con otro módulo'), ('t_inac', 'inactivo')]:
        ss = Sesion()
        ss.login(u)
        s1, _ = ss.req('GET', f'{GW}?r=catalogos')
        s2, _ = ss.req('GET', f'{GW}?r=areas')
        T(f'{desc}: catalogos denegado ({s1}) y areas denegado ({s2})', s1 in (401, 403) and s2 in (401, 403), (s1, s2))

    print('== Nivel ver')
    ver = Sesion()
    ver.login('t_ver')
    s, b = ver.req('GET', f'{GW}?r=catalogos')
    T('ver: catalogos → 200', s == 200 and 'criterios' in b, s)
    s, _ = ver.req('GET', f'{GW}?r=consolidado')
    T('ver: consolidado → 200', s == 200, s)
    s, _ = ver.req('GET', f'{GW}?r=')
    T('ver: inventario → 200', s == 200, s)
    s, _ = ver.req('GET', f'{GW}?r=areas')
    T('ver: GET areas → 403', s == 403, s)
    s, _ = ver.req('GET', f'{GW}?r=brigadistas')
    T('ver: GET brigadistas → 403', s == 403, s)
    s, _ = ver.req('POST', f'{GW}?r=areas', {'nombre': 'Intento'}, ORIG)
    T('ver: POST areas → 403', s == 403, s)
    s, _ = ver.req('PUT', f'{GW}?r=1', {}, ORIG)
    T('ver: PUT extintor → 403', s == 403, s)
    s, _ = ver.req('POST', f'{GW}?r=inspecciones', {}, ORIG)
    T('ver: POST inspecciones admin → 403', s == 403, s)

    print('== Nivel gestionar y admin_ti')
    for u in ('t_gest', 't_admin'):
        g = Sesion()
        g.login(u)
        s, b = g.req('GET', f'{GW}?r=areas')
        T(f'{u}: GET areas → 200 con áreas', s == 200 and 'nombre' in b, s)
        s, b = g.req('GET', f'{GW}?r=brigadistas')
        T(f'{u}: GET brigadistas → 200', s == 200, s)
        s, b = g.req('POST', f'{GW}?r=areas', {'nombre': f'Area prueba {u} {SUF}'}, ORIG)
        T(f'{u}: POST areas (Origin propio) → 200', s == 200, (s, b[:100]))
        aid = json.loads(b)['id'] if s == 200 else 0
        s, _ = g.req('PUT', f'{GW}?r=areas/{aid}', {'nombre': f'Area prueba {u} {SUF} b', 'activo': False}, ORIG)
        T(f'{u}: PUT areas → 200', s == 200, s)

    g = Sesion()
    g.login('t_gest')
    print('== CSRF, lista blanca y entrada')
    s, _ = g.req('POST', f'{GW}?r=areas', {'nombre': 'csrf'}, {'Origin': 'http://evil.example'})
    T('POST con Origin ajeno → 403', s == 403, s)
    s, _ = g.req('POST', f'{GW}?r=areas', {'nombre': 'csrf'})
    T('POST sin Origin ni Referer → 403', s == 403, s)
    s, _ = g.req('POST', f'{GW}?r=areas', {'nombre': f'ref {SUF}'}, {'Referer': f'http://{HOST}/modules/prevencion/'})
    T('POST con Referer propio → 200', s == 200, s)
    s, _ = g.req('DELETE', f'{GW}?r=areas/1', None, ORIG)
    T('DELETE no existe → 404', s == 404, s)
    for ruta in ('publico/catalogos', '../../otra', 'areas/x', 'x', '1/2/3', 'areas/1/algo', '%2e%2e/x'):
        s, _ = g.req('GET', f'{GW}?r={urllib.parse.quote(ruta, safe="%/.")}')
        T(f'ruta fuera de la lista blanca «{ruta}» → 404', s == 404, s)
    s, _ = g.req('GET', f'{GW}?r=consolidado&periodo=2026-10&x=%3Cscript%3E%0d%0aX-Evil:1')
    T('query con caracteres raros se reenvía solo codificada (sin inyección de cabeceras) → 200', s == 200, s)
    s, _ = g.req('GET', f'{GW}?r=consolidado&q=' + 'a' * 600)
    T('query demasiado larga → 400', s == 400, s)
    s, _ = g.req('POST', f'{GW}?r=areas', {'nombre': 'a' * 1_100_000}, ORIG)
    T('cuerpo > 1 MB → 413', s == 413, s)
    s, b = g.req('GET', f'{GW}?r=consolidado&periodo=2026-10')
    T('filtros legítimos se reenvían → 200', s == 200, s)

    print('== Sin fugas de claves')
    paginas = [
        '/modules/prevencion/extintores/inspeccion/', '/modules/prevencion/extintores/configuracion/', '/modules/prevencion/extintores/inventario/',
        '/modules/prevencion/extintores/admin/', '/modules/prevencion/', '/assets/js/prevencion/extintores-comun.js',
        '/assets/js/prevencion/extintores-inspeccion.js', '/assets/js/prevencion/extintores-configuracion.js',
        '/assets/js/prevencion/extintores-inventario.js', '/assets/js/prevencion/extintores-admin.js']
    fuga = []
    for p in paginas:
        s, b = g.req('GET', p)
        for tok in (KEY, 'clave-admin-vieja', 'X-Admin-Key', 'DELETE_KEY', 'SIGNING_KEY'):
            if tok in b:
                fuga.append((p, tok))
    T('ninguna página/JS de Extintores contiene claves', not fuga, fuga)

    print('== Páginas públicas y privadas')
    pub = Sesion()
    s, b = pub.req('GET', '/modules/prevencion/extintores/inspeccion/')
    T('formulario público sin login → 200', s == 200 and 'Inspección de extintores' in b, s)
    s, _ = pub.req('GET', '/modules/prevencion/extintores/configuracion/')
    T('configuración sin login → redirige/deniega', s in (302, 401, 403), s)
    s, _ = pub.req('GET', '/modules/prevencion/extintores/admin/')
    T('panel sin login → redirige/deniega', s in (302, 401, 403), s)
    s, _ = ver.req('GET', '/modules/prevencion/extintores/configuracion/')
    T('configuración con nivel ver → 403', s == 403, s)
    s, _ = g.req('GET', '/modules/prevencion/extintores/configuracion/')
    T('configuración con nivel gestionar → 200', s == 200, s)
    s, _ = ver.req('GET', '/modules/prevencion/extintores/admin/')
    T('panel con nivel ver → 200', s == 200, s)
finally:
    pass

print(f'\n{len(RES)} comprobaciones, {RES.count(False)} fallas')
sys.exit(1 if False in RES else 0)
