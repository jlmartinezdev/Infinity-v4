# En .env
WISPHUB_API_KEY=tu_clave_api
WISPHUB_BASE_URL=https://api.wisphub.net

# Probar sin guardar
php artisan wisphub:importar-clientes --dry-run --max=10

# Importar solo activos, máximo 200
php artisan wisphub:importar-clientes --estado=1 --max=200

# Importar todos (paginado)
php artisan wisphub:importar-clientes

Importar servicios: usar getClientes() (incluye IP, usuario RB, plan, etc.), mapear planes WispHub → planes en Infinity y crear/actualizar registros en servicios.
Pantalla en el panel: página “Importar desde WispHub” que llame a un controlador y ejecute la importación (o encolar un job) y muestre progreso o resultado.
Evitar duplicados: guardar id_servicio de WispHub en una columna (por ejemplo wisphub_id en clientes) para no depender solo de la cédula.
Si quieres, el siguiente paso puede ser la importación de servicios o la pantalla en el panel para lanzar la importación desde la web.