# Reporte de migración — Correlativos Perfiles y Moldes

Generado a partir de: `C:\Users\dcarrasco\Desktop\Proyectos\workspacefaret\docs\CORRELATIVOS PERFILES _ MOLDES (PLA-DES-CPM-S).xlsx`

Lote de importación: `MIGRACION_2026-08-13`

## PERFILES
- Filas escaneadas: 9002
- Filas importadas (con CLIENTE real): **8068**
- Filas ignoradas por ser placeholder/vacías: 934
- Último código real: P008078 → siguiente correlativo: **P008079**
- Códigos duplicados históricos (4): {'P000002': 2, 'P000010': 2, 'P000118': 2, 'P004449': 2}
- Código NULO: [(5907, 'NULO')]
- Códigos con versión (-V2/-V3): 34 filas
- N° DE CAJA con valores no numéricos: 221 filas
- UNID POR CAJA con valores no numéricos: 1446 filas
- Fechas no interpretables (quedan NULL, valor original preservado en fecha_raw): 27 filas
    - fila 635: '25/05718'
    - fila 684: '15/16/18'
    - fila 1999: '12//11/19'
    - fila 2795: '15/09720'
    - fila 3132: '20/21/21'
    - fila 3706: '29/06/021'
    - fila 3707: '29/06/021'
    - fila 3708: '29/06/021'
    - fila 3709: '29/06/021'
    - fila 3710: '29/06/021'
    - fila 5209: '29/0722'
    - fila 5437: '4.0'
    - fila 5463: '13/096/22'
    - fila 5464: '13/096/22'
    - fila 5466: '13/096/22'
    - fila 5556: '29/096/22'
    - fila 5557: '29/096/22'
    - fila 6198: '14/023/23'
    - fila 6874: '27/07/823'
    - fila 6875: '27/07/823'
    - fila 6878: 'PERFIL NULO'
    - fila 7348: '2002-06-09 00:00:00'
    - fila 7471: 'EST. HAVANA CLUB ESPCIAL'
    - fila 7814: '13/11/285'
    - fila 7815: '13/11/285'
    - fila 7816: '13/11/285'
    - fila 7817: '13/11/285'
- ESTADO con error de fórmula (#REF!, guardado como NULL): filas [4379]
- Filas con CLIENTE vacío pero importadas por tener otro dato real (desc/fecha): 2
    - fila 1834 (P001828): descripcion='ELIMINADO'
    - fila 5029 (P005023): descripcion='EST 1810'

## MOLDES NUEVOS (Repetitivo)
- Filas escaneadas: 1071
- Filas importadas (con CLIENTE real): **1002**
- Filas ignoradas por ser placeholder/vacías: 69
- Último número real: 1002 → siguiente correlativo: **M001003**
- Duplicados: {}
- Códigos con formato especial/histórico (no matchean el patrón moderno): 0 filas
- ANOMALÍA: OPERADOR contiene una fecha en vez de un nombre (9 filas) — preservado tal cual, no se inventó el operador:
    - fila 891 (M000887): 2026-06-02 00:00:00
    - fila 892 (M000888): 2026-09-02 00:00:00
    - fila 893 (M000889): 2026-09-02 00:00:00
    - fila 894 (M000890): 2026-09-02 00:00:00
    - fila 895 (M000891): 2026-10-02 00:00:00
    - fila 896 (M000892): 2026-10-02 00:00:00
    - fila 897 (M000893): 2026-12-02 00:00:00
    - fila 898 (M000894): 2026-12-02 00:00:00
    - fila 899 (M000895): 2026-12-02 00:00:00
- Fechas de ingreso no interpretables: 15 filas
    - fila 135: '24/6'
    - fila 136: '24/6'
    - fila 137: '24/6'
    - fila 143: '28/6'
    - fila 178: '19/7'
    - fila 181: '22/7'
    - fila 192: '26/7'
    - fila 193: '26/7'
    - fila 194: '26/7'
    - fila 586: '27/05'
    - fila 668: '14/08725'
    - fila 669: '14/08725'
    - fila 670: '14/08725'
    - fila 671: '14/08725'
    - fila 905: '2702/2026'

## MOLDE NO REPETITIVO
- Filas escaneadas: 1072
- Filas importadas (con CLIENTE real): **738**
- Filas ignoradas por ser placeholder/vacías: 334
- Último número real: 737 → siguiente correlativo: **NR000738**
- Duplicados: {}
- Códigos con formato especial/histórico (no matchean el patrón moderno): 1 filas
    - fila 170: 'NR000165 (MICA)'
- Fechas de ingreso no interpretables: 2 filas
    - fila 307: '14/05/205'
    - fila 308: '14/05/205'
- Fechas de entrega no interpretables: 3 filas
    - fila 307: '14/05/205'
    - fila 308: '14/05/205'
    - fila 673: '#VALUE!'

## MOLDES (OBSOLETO, histórico)
- Filas escaneadas: 1071
- Filas importadas (con CLIENTE real): **171**
- Filas ignoradas por ser placeholder/vacías: 900
- Duplicados: {}
- Códigos con formato especial/histórico (no matchean el patrón moderno): 109 filas
    - fila 9: 'M00005'
    - fila 12: 'M00008'
    - fila 13: 'M00009'
    - fila 15: 'M00011'
    - fila 18: 'M00014'
    - fila 19: 'M00015'
    - fila 24: 'M00020'
    - fila 25: 'M00021'
    - fila 26: 'M00022'
    - fila 27: 'M00023'
    - fila 28: 'M00024'
    - fila 29: 'M00025'
    - fila 30: 'M00026'
    - fila 31: 'M00027'
    - fila 32: 'M00028'
    - fila 35: 'M00031'
    - fila 36: 'M00032'
    - fila 37: 'M00033'
    - fila 38: 'M00034'
    - fila 39: 'M00035'
    - fila 40: 'M00036'
    - fila 45: 'M00041'
    - fila 46: 'M00042'
    - fila 48: 'M00044'
    - fila 50: 'M00046'
    - fila 52: 'M00048'
    - fila 53: 'M00049'
    - fila 55: 'M00051'
    - fila 57: 'M00053'
    - fila 58: 'M00054'
- Fechas de ingreso no interpretables: 0 filas

## Hojas NO migradas (solo inventariadas)
Corresponden al proceso 'Solicitud de Desarrollos 2023', distinto de Correlativos Perfiles/Moldes:
- 10.- Octubre: 337 filas (máx. fila con datos, sin depurar placeholders)
- 08.-AGOSTO: 395 filas (máx. fila con datos, sin depurar placeholders)
- 09.- Septiembre: 242 filas (máx. fila con datos, sin depurar placeholders)
- 07.-JULIO: 295 filas (máx. fila con datos, sin depurar placeholders)
- 06.- JUNIO: 203 filas (máx. fila con datos, sin depurar placeholders)
- 05.-MAYO: 292 filas (máx. fila con datos, sin depurar placeholders)
- 04.-ABRIL: 261 filas (máx. fila con datos, sin depurar placeholders)
- 03.-MARZO: 1308 filas (máx. fila con datos, sin depurar placeholders)
- 02.- FEBRERO: 603 filas (máx. fila con datos, sin depurar placeholders)
- Otras hojas auxiliares no migradas: ['Programa', 'PLANTILLA'] (basadas en fórmulas / plantilla, no son datos operacionales)