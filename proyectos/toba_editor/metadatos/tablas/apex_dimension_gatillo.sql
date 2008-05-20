
------------------------------------------------------------
-- apex_dimension_gatillo
------------------------------------------------------------

--- INICIO Grupo de desarrollo 0
INSERT INTO apex_dimension_gatillo (proyecto, dimension, gatillo, tipo, orden, tabla_rel_dim, columnas_rel_dim, tabla_gatillo, ruta_tabla_rel_dim) VALUES (
	'toba_editor', --proyecto
	'9', --dimension
	'1', --gatillo
	'directo', --tipo
	'1', --orden
	'ref_deportes', --tabla_rel_dim
	'id', --columnas_rel_dim
	NULL, --tabla_gatillo
	NULL  --ruta_tabla_rel_dim
);
INSERT INTO apex_dimension_gatillo (proyecto, dimension, gatillo, tipo, orden, tabla_rel_dim, columnas_rel_dim, tabla_gatillo, ruta_tabla_rel_dim) VALUES (
	'toba_editor', --proyecto
	'9', --dimension
	'4', --gatillo
	'directo', --tipo
	'2', --orden
	'ref_persona_deportes', --tabla_rel_dim
	'deportes', --columnas_rel_dim
	NULL, --tabla_gatillo
	NULL  --ruta_tabla_rel_dim
);
--- FIN Grupo de desarrollo 0