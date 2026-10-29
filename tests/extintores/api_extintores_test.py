"""Pruebas de la API de Extintores (areas, brigadistas, formulario publico y administracion) contra una BD LOCAL de prueba.

    python -I tests/extintores/api_extintores_test.py <cadena_de_conexion_sqlserver_local> [ruta_dll]

Requisitos: BD local (p. ej. LocalDB) con los scripts 2026-10-19_prevencion_extintores*.sql y
2026-10-29_prevencion_areas_brigadistas.sql ya aplicados. NUNCA apuntar a produccion: el script escribe datos de prueba.
Levanta Formularios.Api (Release) en un puerto libre con una clave de administracion ficticia.
"""
import base64
import hashlib
import hmac
import http.client
import json
import os
import socket
import subprocess
import sys
import time

sys.stdout.reconfigure(encoding='utf-8', errors='replace')
CONN = sys.argv[1]
DLL = sys.argv[2] if len(sys.argv) > 2 else os.path.abspath(os.path.join(
    os.path.dirname(__file__), '..', '..', '..', 'Formularios.Api', 'bin', 'Release', 'net8.0', 'Formularios.Api.dll'))
KEY = 'clave-firma-local-de-prueba'      # PrevencionSecurity:SigningKey (ficticia)
OLD_ADMIN_KEY = 'clave-admin-vieja'
RES = []


def T(nombre, ok, detalle=''):
    RES.append(bool(ok))
    print(('  OK    ' if ok else '  FALLA ') + nombre + (f'  [{detalle}]' if detalle and not ok else ''))


def puerto():
    s = socket.socket()
    s.bind(('127.0.0.1', 0))
    p = s.getsockname()[1]
    s.close()
    return p


PORT = puerto()
env = dict(os.environ, ASPNETCORE_ENVIRONMENT='Production', DOTNET_ENVIRONMENT='Production',
           **{'ConnectionStrings__SqlServerConnection': CONN, 'PrevencionSecurity__SigningKey': KEY, 'AdminSecurity__DeleteKey': OLD_ADMIN_KEY,
              'Cors__AllowedOrigins__0': 'http://localhost:8080', 'Smtp__Host': '127.0.0.1', 'Smtp__Port': '1', 'FpsApi__BaseUrl': '', 'FpsApi__ApiKey': ''})
srv = subprocess.Popen(['dotnet', DLL, '--urls', f'http://127.0.0.1:{PORT}'], cwd=os.path.abspath(os.path.join(os.path.dirname(DLL), '..', '..', '..')),
                       env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
BASE = '/api/prevencion/extintores'


def b64u(b):
    return base64.urlsafe_b64encode(b).rstrip(b'=').decode()


def firmar(metodo, ruta_query, nivel, usuario='miguel', ts=None, clave=KEY):
    ts = str(int(time.time()) if ts is None else ts)
    u = b64u(usuario.encode())
    msg = chr(10).join([ts, metodo, ruta_query, u, nivel])
    sig = b64u(hmac.new(clave.encode(), msg.encode(), hashlib.sha256).digest())
    return {'X-WF-Ts': ts, 'X-WF-User': u, 'X-WF-Nivel': nivel, 'X-WF-Sig': sig}


def req(metodo, ruta, cuerpo=None, key=None, crudo=None, nivel='gestionar', extra=None):
    c = http.client.HTTPConnection('127.0.0.1', PORT, timeout=60)
    h = {}
    if key:   # llamada "firmada por Workspace" con el nivel indicado
        h.update(firmar(metodo, BASE + ruta, nivel))
    h.update(extra or {})
    data = crudo
    if cuerpo is not None:
        data = json.dumps(cuerpo).encode()
    if data is not None:
        h['Content-Type'] = 'application/json'
    c.request(metodo, BASE + ruta, body=data, headers=h)
    r = c.getresponse()
    txt = r.read().decode('utf-8', 'replace')
    c.close()
    try:
        return r.status, json.loads(txt)
    except Exception:
        return r.status, txt


A = lambda m, r, b=None: req(m, r, b, key=KEY)                  # Workspace con nivel gestionar
V = lambda m, r, b=None: req(m, r, b, key=KEY, nivel='ver')      # Workspace con nivel ver

try:
    for _ in range(80):
        try:
            socket.create_connection(('127.0.0.1', PORT), timeout=0.3).close()
            break
        except OSError:
            time.sleep(0.5)

    print('== Públicos: datos mínimos')
    s, cat = req('GET', '/publico/catalogos')
    T('catálogo público sin clave → 200', s == 200, str(s))
    T('13 criterios, con "Prueba hidrostática" aplicable solo a CO2', len(cat['criterios']) == 13 and any(c['orden'] == 8 and c['aplicaTipos'] == 'CO2' for c in cat['criterios']))
    T('el catálogo público solo trae plantas, criterios, brigadistas y periodo', set(cat.keys()) == {'plantas', 'criterios', 'brigadistas', 'periodoActual'})
    T('brigadistas públicos: solo id y nombre', all(set(b.keys()) == {'id', 'nombre'} for b in cat['brigadistas']) and len(cat['brigadistas']) == 19)
    b1, b2, b3 = cat['brigadistas'][0]['id'], cat['brigadistas'][1]['id'], cat['brigadistas'][2]['id']

    s, ext_pub = req('GET', f'/publico/extintores?brigadistaId={b2}')
    T('sin áreas asignadas el brigadista ve todos los extintores (216)', s == 200 and len(ext_pub) == 216, f'{s} {len(ext_pub) if isinstance(ext_pub, list) else ext_pub}')
    campos = {'id', 'plantaId', 'plantaNombre', 'numero', 'ubicacion', 'tipo', 'capacidadKg', 'areaId', 'areaNombre', 'inspeccionado'}
    T('extintor público: solo campos mínimos (sin vencimiento, usuario ni historial)', all(set(e.keys()) == campos for e in ext_pub))
    s, _ = req('GET', '/publico/extintores')
    T('sin brigadistaId → 400', s == 400, str(s))

    print('== CORS: preflight de las rutas con varios verbos')
    for ruta in ('', '/areas', '/brigadistas', '/publico/inspecciones', '/inspecciones'):
        c = http.client.HTTPConnection('127.0.0.1', PORT, timeout=30)
        c.request('OPTIONS', BASE + ruta, headers={'Origin': 'http://localhost:8080', 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'x-admin-key,content-type'})
        r = c.getresponse(); r.read(); c.close()
        T(f'preflight OPTIONS {ruta or "/"} → 204 con CORS', r.status == 204 and r.getheader('Access-Control-Allow-Origin') == 'http://localhost:8080', str(r.status))

    print('== Rutas administrativas sin firma / firma ajena / clave antigua → 401')
    rutas = [('GET', '/catalogos'), ('GET', ''), ('GET', '/1'), ('GET', '/1/inspecciones'), ('GET', '/inspecciones/1'), ('GET', '/consolidado'),
             ('GET', '/areas'), ('GET', '/brigadistas'), ('POST', '/areas'), ('POST', '/brigadistas'), ('PUT', '/areas/1'), ('PUT', '/brigadistas/1'),
             ('POST', ''), ('PUT', '/1'), ('POST', '/inspecciones')]
    for m, r in rutas:
        s1, _ = req(m, r, {} if m != 'GET' else None)
        s2, _ = req(m, r, {} if m != 'GET' else None, extra=firmar(m, BASE + r, 'gestionar', clave='otra-clave'))
        s3, _ = req(m, r, {} if m != 'GET' else None, extra={'X-Admin-Key': OLD_ADMIN_KEY})
        T(f'{m} {r or "/"} sin firma, con firma ajena y con la X-Admin-Key antigua → 401', s1 == 401 and s2 == 401 and s3 == 401, f'{s1}/{s2}/{s3}')

    print('== Niveles y manipulación de la firma')
    for m, r, cuerpo in [('GET', '/catalogos', None), ('GET', '', None), ('GET', '/consolidado', None), ('GET', '/1', None), ('GET', '/1/inspecciones', None)]:
        s1, _ = V(m, r, cuerpo)
        T(f'nivel ver puede consultar {m} {r or "/"}', s1 == 200 or s1 == 404, str(s1))
    for m, r in [('GET', '/areas'), ('GET', '/brigadistas'), ('POST', '/areas'), ('POST', '/brigadistas'), ('POST', ''), ('PUT', '/1'),
                 ('PUT', '/areas/1'), ('PUT', '/brigadistas/1'), ('POST', '/inspecciones')]:
        s1, _ = V(m, r, {} if m != 'GET' else None)
        T(f'nivel ver NO puede {m} {r or "/"} → 403', s1 == 403, str(s1))
    # La firma cubre método, ruta, query, usuario y nivel: alterar cualquiera invalida
    f_ver = firmar('GET', BASE + '/consolidado', 'ver')
    s1, _ = req('GET', '/consolidado', extra={**f_ver, 'X-WF-Nivel': 'gestionar'})
    T('escalar el nivel (ver→gestionar) con la firma de ver → 401', s1 == 401, str(s1))
    s1, _ = req('GET', '/areas', extra=f_ver)
    T('reusar la firma de otra ruta → 401', s1 == 401, str(s1))
    s1, _ = req('GET', '/consolidado?plantaId=1', extra=f_ver)
    T('agregar query a una firma ya emitida → 401', s1 == 401, str(s1))
    s1, _ = req('PUT', '/1', {}, extra=firmar('GET', BASE + '/1', 'gestionar'))
    T('cambiar el método con la firma de otro → 401', s1 == 401, str(s1))
    s1, _ = req('GET', '/catalogos', extra={**firmar('GET', BASE + '/catalogos', 'ver'), 'X-WF-User': b64u(b'otro')})
    T('cambiar el usuario firmado → 401', s1 == 401, str(s1))
    s1, _ = req('GET', '/catalogos', extra=firmar('GET', BASE + '/catalogos', 'ver', ts=int(time.time()) - 3600))
    T('firma vencida (1 h) → 401', s1 == 401, str(s1))
    s1, _ = req('GET', '/catalogos', extra=firmar('GET', BASE + '/catalogos', 'admin'))
    T('nivel inventado → 401', s1 == 401, str(s1))
    s1, _ = req('GET', '/publico/catalogos', extra={'X-WF-Nivel': 'gestionar'})
    T('las rutas públicas ignoran cualquier cabecera de administración', s1 == 200, str(s1))

    print('== Administración: áreas, brigadistas, asignaciones')
    s, areas = A('GET', '/areas')
    T('17 áreas iniciales', s == 200 and len(areas) == 17, str(s))
    area1, area2 = areas[0]['id'], areas[1]['id']
    s, r = A('POST', '/areas', {'nombre': areas[0]['nombre']})
    T('área duplicada → 400', s == 400 and 'Ya existe' in r, str(r))
    s, r = A('POST', '/areas', {'nombre': '  '})
    T('área sin nombre → 400', s == 400, str(s))
    s, nueva = A('POST', '/areas', {'nombre': 'Area de prueba temporal'})
    T('crear área → 200 con id', s == 200 and nueva['id'] > 0 and nueva['activo'], str(s))
    s, r = A('PUT', f"/areas/{nueva['id']}", {'nombre': 'Area de prueba renombrada', 'activo': False})
    T('renombrar y desactivar área', s == 200 and r['nombre'].endswith('renombrada') and r['activo'] is False)
    s, r = A('PUT', '/areas/999999', {'nombre': 'x'})
    T('área inexistente → 404', s == 404)
    s, r = A('POST', '/brigadistas', {'nombre': 'Brigadista X', 'areaIds': [nueva['id']]})
    T('asignar un área inactiva → 400', s == 400, str(r))
    s, r = A('POST', '/brigadistas', {'nombre': 'Brigadista X', 'areaIds': [999999]})
    T('asignar un área inexistente → 400', s == 400)
    s, r = A('POST', '/brigadistas', {'nombre': cat['brigadistas'][0]['nombre']})
    T('brigadista duplicado → 400', s == 400)

    s, lista = A('GET', '/')
    pqs = [e for e in lista if e['tipo'] == 'PQS' and e['activo']]
    co2 = [e for e in lista if e['tipo'] == 'CO2' and e['activo']]
    T('el inventario trae tipos PQS y CO2', len(pqs) > 3 and len(co2) > 3)
    e1, e2, e3 = pqs[0], pqs[1], co2[0]

    def asignar(e, area_id):
        return A('PUT', f"/{e['id']}", {'plantaId': e['plantaId'], 'ubicacion': e['ubicacion'], 'zona': e['zona'], 'tipo': e['tipo'],
                                         'capacidadKg': e['capacidadKg'], 'vencimiento': e['vencimiento'], 'activo': True, 'areaId': area_id})

    for e, a in ((e1, area1), (e2, area1), (e3, area2)):
        s, r = asignar(e, a)
        T(f'asignar extintor {e["id"]} a área {a}', s == 200 and r['areaId'] == a, str(r))
    s, r = asignar(e1, 999999)
    T('extintor con área inexistente → 400', s == 400)

    s, r = A('PUT', f'/brigadistas/{b1}', {'nombre': cat['brigadistas'][0]['nombre'], 'activo': True, 'areaIds': [area1]})
    T('asignar el área 1 al brigadista 1', s == 200 and r['areaIds'] == [area1], str(r))

    print('== Restricción por área (formulario público)')
    s, vis = req('GET', f'/publico/extintores?brigadistaId={b1}')
    ids = {e['id'] for e in vis}
    T('brigadista con área asignada ve solo sus extintores', s == 200 and ids == {e1['id'], e2['id']}, str(ids))
    s, vis2 = req('GET', f'/publico/extintores?brigadistaId={b2}')
    T('otro brigadista sin áreas sigue viendo todos', len(vis2) == 216)
    s, ars = req('GET', f'/publico/brigadistas/{b1}/areas')
    T('áreas visibles del brigadista restringido: solo la suya', [a['id'] for a in ars] == [area1])
    s, ars2 = req('GET', f'/publico/brigadistas/{b2}/areas')
    T('áreas visibles del no restringido: todas las activas (17)', len(ars2) == 17, str(len(ars2)))
    s, vis3 = req('GET', f'/publico/extintores?brigadistaId={b1}&areaId={area2}')
    T('filtro por un área ajena → vacío', s == 200 and vis3 == [])
    s, r = req('GET', f"/publico/extintores/{e3['id']}?brigadistaId={b1}")
    T('detalle de extintor ajeno → 404', s == 404, str(s))
    s, r = req('GET', f"/publico/extintores/{e1['id']}?brigadistaId={b1}")
    T('detalle de extintor propio → 200 y no hay inspección previa', s == 200 and r['inspeccionado'] is False)

    print('== Registro de inspecciones públicas')
    def resultados(cat_, tipo, modo='SI', extra=None):
        out = []
        for c in cat_['criterios']:
            r_ = {'criterioId': c['id'], 'resultado': modo, 'observacion': None, 'anio': 2025 if c['pideAnio'] else None}
            out.append(r_)
        for o in (extra or []):
            for r_ in out:
                if r_['criterioId'] == o['criterioId']:
                    r_.update(o)
        return out

    crit = {c['orden']: c['id'] for c in cat['criterios']}
    base = {'extintorId': e1['id'], 'brigadistaId': b1, 'resultados': resultados(cat, 'PQS')}
    s, r = req('POST', '/publico/inspecciones', {**base, 'extintorId': e3['id']})
    T('inspeccionar un extintor fuera de tus áreas → 400', s == 400 and 'áreas' in r, str(r))
    s, r = req('POST', '/publico/inspecciones', {**base, 'brigadistaId': None})
    T('sin brigadista → 400', s == 400)
    s, r = req('POST', '/publico/inspecciones', {**base, 'brigadistaId': 999999})
    T('brigadista inexistente → 400', s == 400)
    s, r = req('POST', '/publico/inspecciones', {**base, 'extintorId': 999999})
    T('extintor inexistente → 404', s == 404)
    s, r = req('POST', '/publico/inspecciones', crudo=b'{no es json')
    T('JSON inválido → 400', s == 400, str(s))
    s, r = req('POST', '/publico/inspecciones', {**base, 'resultados': base['resultados'][:-1]})
    T('checklist incompleto → 400', s == 400)
    mala = resultados(cat, 'PQS', extra=[{'criterioId': crit[9], 'resultado': 'NO', 'observacion': None}])
    s, r = req('POST', '/publico/inspecciones', {**base, 'resultados': mala})
    T('«No» sin observación → 400', s == 400 and 'observación' in r, str(r))

    ok_res = resultados(cat, 'PQS', extra=[{'criterioId': crit[9], 'resultado': 'NO', 'observacion': 'Pintura descascarada'},
                                           {'criterioId': crit[8], 'resultado': 'SI', 'anio': 2025}])   # 8 = hidrostática CO2: el servidor la fuerza a NA
    s, r = req('POST', '/publico/inspecciones', {**base, 'resultados': ok_res})
    T('inspección válida → 201 con acuse mínimo', s == 201 and set(r.keys()) == {'id', 'resultadoGeneral'} and r['resultadoGeneral'] == 'CON_HALLAZGOS', f'{s} {r}')
    insp_id = r['id'] if isinstance(r, dict) else 0
    s, det = A('GET', f'/inspecciones/{insp_id}')
    por = {x['orden']: x for x in det['resultados']}
    T('el inspector registrado es el nombre del brigadista (no lo que mande el cliente)', det['usuario'] == cat['brigadistas'][0]['nombre'] and det['brigadistaId'] == b1)
    T('13 resultados guardados', len(det['resultados']) == 13)
    T('PQS: «Prueba hidrostática» (solo CO2) queda en No aplica aunque el cliente mande Sí', por[8]['resultado'] == 'NA' and por[8]['anio'] is None)
    T('PQS: «Manómetro» (solo PQS) sí se evalúa', por[4]['resultado'] == 'SI')
    T('la observación del «No» se guarda', por[9]['resultado'] == 'NO' and por[9]['observacion'] == 'Pintura descascarada')

    s, r = req('POST', '/publico/inspecciones', {'extintorId': e3['id'], 'brigadistaId': b2, 'resultados': resultados(cat, 'CO2', extra=[{'criterioId': crit[4], 'resultado': 'SI'}])})
    T('inspección CO2 por brigadista sin restricción → 201', s == 201, str(r))
    s, det = A('GET', f"/inspecciones/{r['id']}")
    por = {x['orden']: x for x in det['resultados']}
    T('CO2: «Manómetro» (solo PQS) queda en No aplica y «Prueba hidrostática» con año se guarda', por[4]['resultado'] == 'NA' and por[8]['resultado'] == 'SI' and por[8]['anio'] == 2025)
    s, r = req('POST', '/publico/inspecciones', {'extintorId': e3['id'], 'brigadistaId': b2,
                                                   'resultados': resultados(cat, 'CO2', extra=[{'criterioId': crit[8], 'resultado': 'SI', 'anio': None}])})
    T('CO2: hidrostática en «Sí» sin año → 400', s == 400, str(r))

    s, r = req('POST', '/publico/inspecciones', {'extintorId': e1['id'], 'brigadistaId': b1, 'resultados': resultados(cat, 'PQS')})
    T('reenviar al instante la misma inspección → 400 (antiabuso)', s == 400 and 'hace instantes' in r, str(r))
    s, r = req('GET', f"/publico/extintores/{e1['id']}?brigadistaId={b1}")
    T('el extintor aparece como inspeccionado este mes', r['inspeccionado'] is True)
    s, cons = A('GET', '/consolidado')
    f = [x for x in cons if x['id'] == e1['id']][0]
    T('el consolidado del panel refleja la inspección y el área', f['inspeccionId'] == insp_id and f['areaId'] == area1 and f['inspeccionResultado'] == 'CON_HALLAZGOS')

    print('== Historial intacto y admin')
    s, hist = A('POST', '/inspecciones', {'extintorId': e2['id'], 'usuario': 'Inspector historico', 'resultados': resultados(cat, 'PQS')})
    T('el alta administrativa (sin brigadista) sigue funcionando y deja brigadistaId vacío', s == 201 and hist['brigadistaId'] is None and hist['usuario'] == 'Inspector historico', str(hist))
    s, h = A('GET', f"/{e2['id']}/inspecciones")
    T('el historial del extintor se lista con la clave', s == 200 and len(h) == 1)

    print('== Baja de extintor y brigadista inactivo')
    s, r = A('PUT', f"/{e2['id']}", {'plantaId': e2['plantaId'], 'ubicacion': e2['ubicacion'], 'zona': e2['zona'], 'tipo': e2['tipo'],
                                      'capacidadKg': e2['capacidadKg'], 'vencimiento': e2['vencimiento'], 'activo': False, 'areaId': area1})
    s, r = req('POST', '/publico/inspecciones', {'extintorId': e2['id'], 'brigadistaId': b1, 'resultados': resultados(cat, 'PQS')})
    T('extintor dado de baja no se puede inspeccionar → 400', s == 400, str(r))
    s, r = A('PUT', f'/brigadistas/{b3}', {'nombre': cat['brigadistas'][2]['nombre'], 'activo': False, 'areaIds': []})
    s, cat2 = req('GET', '/publico/catalogos')
    T('un brigadista inactivo desaparece de la lista pública', all(b['id'] != b3 for b in cat2['brigadistas']))
    s, r = req('POST', '/publico/inspecciones', {'extintorId': e3['id'], 'brigadistaId': b3, 'resultados': resultados(cat, 'CO2')})
    T('un brigadista inactivo no puede registrar → 400', s == 400, str(r))
    s, r = A('PUT', f'/brigadistas/{b1}', {'nombre': cat['brigadistas'][0]['nombre'], 'activo': True, 'areaIds': []})
    s, vis = req('GET', f'/publico/extintores?brigadistaId={b1}')
    T('al quitar sus áreas el brigadista vuelve a ver todos (liberado)', len(vis) == 215, str(len(vis)))
    print('== Antiabuso del formulario público')
    codigos = []
    for _ in range(160):
        c = http.client.HTTPConnection('127.0.0.1', PORT, timeout=30)
        c.request('POST', BASE + '/publico/inspecciones', body=json.dumps({'extintorId': 1, 'brigadistaId': 999999, 'resultados': []}).encode(), headers={'Content-Type': 'application/json'})
        r = c.getresponse(); r.read(); c.close()
        codigos.append(r.status)
    T('una red puede enviar un volumen normal sin bloqueo (primeros 100 → sin 429)', 429 not in codigos[:100], str(codigos[:100].count(429)))
    T('pasado el límite (150/10 min por IP) responde 429', codigos[-1] == 429, str(codigos[-5:]))
finally:
    srv.terminate()
    try:
        srv.wait(10)
    except Exception:
        srv.kill()

print(f'\n{len(RES)} comprobaciones, {RES.count(False)} fallas')
sys.exit(1 if False in RES else 0)
