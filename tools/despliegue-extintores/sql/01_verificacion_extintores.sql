-- EXTINTORES - Verificacion SOLO LECTURA (no modifica nada). Ejecutar en formularios_db ANTES y DESPUES del script
-- 2026-10-29_prevencion_areas_brigadistas.sql y guardar ambas salidas. Las filas "HISTORICO" deben ser identicas.
USE [formularios_db];
SET NOCOUNT ON;

SELECT 'HISTORICO' AS bloque, 'pr_extintores' AS tabla, COUNT(*) AS filas, CHECKSUM_AGG(CHECKSUM(id, planta_id, numero, tipo, ubicacion)) AS checksum FROM pr_extintores
UNION ALL SELECT 'HISTORICO', 'pr_inspecciones', COUNT(*), CHECKSUM_AGG(CHECKSUM(id, extintor_id, periodo, usuario, fecha_inspeccion)) FROM pr_inspecciones
UNION ALL SELECT 'HISTORICO', 'pr_inspeccion_resultados', COUNT(*), CHECKSUM_AGG(CHECKSUM(id, inspeccion_id, criterio_id, resultado)) FROM pr_inspeccion_resultados
UNION ALL SELECT 'HISTORICO', 'pr_criterios', COUNT(*), CHECKSUM_AGG(CHECKSUM(id, orden)) FROM pr_criterios
UNION ALL SELECT 'HISTORICO', 'pr_plantas', COUNT(*), CHECKSUM_AGG(CHECKSUM(id, codigo)) FROM pr_plantas;

SELECT 'OBJETOS NUEVOS' AS bloque,
       (SELECT COUNT(*) FROM sys.tables WHERE name IN ('pr_areas','pr_brigadistas','pr_brigadista_areas')) AS tablas_nuevas_de_3,
       CASE WHEN COL_LENGTH('pr_extintores','area_id') IS NULL THEN 0 ELSE 1 END AS col_extintores_area_id,
       CASE WHEN COL_LENGTH('pr_inspecciones','brigadista_id') IS NULL THEN 0 ELSE 1 END AS col_inspecciones_brigadista_id;

IF OBJECT_ID('pr_areas') IS NOT NULL
    SELECT 'DATOS NUEVOS' AS bloque, (SELECT COUNT(*) FROM pr_areas) AS areas, (SELECT COUNT(*) FROM pr_brigadistas) AS brigadistas,
           (SELECT COUNT(*) FROM pr_brigadista_areas) AS asignaciones,
           (SELECT COUNT(*) FROM pr_extintores WHERE area_id IS NOT NULL) AS extintores_con_area,
           (SELECT COUNT(*) FROM pr_inspecciones WHERE brigadista_id IS NOT NULL) AS inspecciones_con_brigadista;
