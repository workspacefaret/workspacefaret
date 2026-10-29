-- EXTINTORES - Respaldo previo (ejecutar en SSMS como administrador, conectado a SAP_NEW / .230).
-- Respaldo COPY_ONLY (no altera la cadena de respaldos habitual) con checksum y verificacion.
-- 1) Ajustar @ruta a una carpeta EXISTENTE del servidor SQL (.230) con espacio libre. 2) Ejecutar completo.
DECLARE @ruta NVARCHAR(400) = N'D:\Backups\formularios_db_pre_extintores_20261029.bak';   -- <== AJUSTAR

BACKUP DATABASE [formularios_db] TO DISK = @ruta
    WITH COPY_ONLY, COMPRESSION, CHECKSUM, INIT, STATS = 10,
         NAME = N'formularios_db pre despliegue Extintores 2026-10-29';

RESTORE VERIFYONLY FROM DISK = @ruta WITH CHECKSUM;   -- debe terminar con "The backup set on file 1 is valid."
