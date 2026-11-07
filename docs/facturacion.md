# Facturación interna — reglas, cálculos y puntos a mejorar

Documento para analizar el ciclo de facturas internas, recibos y el resumen mensual del dashboard. No describe el armado de la factura electrónica SIFEN (`/facturas`): ese flujo es aparte y no mueve `cobros_resumen`.

> **Última revisión:** 6 de noviembre de 2026  
> **Fuente:** `app/Support/CobrosMesVentana.php`, `app/Services/CobrosResumenService.php`, `app/Services/FacturacionService.php`, `app/Http/Controllers/FacturaInternaController.php`, `app/Http/Controllers/CobroController.php`, `app/Http/Controllers/FacturacionDashboardController.php`.

---

## Índice

1. [Qué mira cada pantalla](#qué-mira-cada-pantalla)
2. [El ciclo del mes](#el-ciclo-del-mes)
3. [Saldo de una factura](#saldo-de-una-factura)
4. [Facturado, cobrado y pendiente](#facturado-cobrado-y-pendiente)
5. [Qué se guarda y qué se recalcula](#qué-se-guarda-y-qué-se-recalcula)
6. [Alta, edición, nota de crédito y borrado](#alta-edición-nota-de-crédito-y-borrado)
7. [Cobros y recibos](#cobros-y-recibos)
8. [Tareas automáticas y comandos](#tareas-automáticas-y-comandos)
9. [Cómo exportar para analizar](#cómo-exportar-para-analizar)
10. [Dónde el número se puede desviar](#dónde-el-número-se-puede-desviar)
11. [Mejoras concretas](#mejoras-concretas)

---

## Qué mira cada pantalla

| Pantalla | Ruta | Qué muestra |
|---|---|---|
| Dashboard de facturación | `/facturacion/dashboard` | Seis meses de `cobros_resumen`: facturado, cobrado y pendiente **guardados**. |
| Facturas internas | `/factura-internas` | Listado vivo. El pendiente de la lista usa el saldo de cada factura. |
| Notas de crédito | `/factura-internas/notas-credito` | Notas emitidas sobre facturas internas. |
| Pendiente de pago | `/factura-internas/pendientes` | Saldos impagos. El cartel «Pendientes del mes» de Cobros cuenta solo las **vencidas** del ciclo. |
| Cobros | `/cobros/servicios` | Buscador de cliente por cédula o nombre para registrar un cobro. |
| Cobros y recibos | `/cobros` | Recibos. El total verde sigue los filtros de esa pantalla. El cartel «Cobros del mes» es el ciclo, no el filtro de fechas si el usuario las cambia. |
| Saldo a favor | `/facturacion/cobros-saldo-favor` | Parte del recibo que no quedó aplicada a una factura. |

El dashboard **no** suma las facturas en el momento de abrir. Lee la fila de `cobros_resumen` del mes (`total_facturado`, `total_cobrado`, `total_pendiente`).

---

## El ciclo del mes

El mes del dashboard no es el mes calendario de la factura. Una factura creada en octubre cuenta en **noviembre**.

Para el mes de referencia `M` (por ejemplo `2026-11-01`):

| Concepto | Ventana |
|---|---|
| Facturas del ciclo | `created_at` de todo el mes calendario anterior. Noviembre 2026 = facturas creadas del 1 al 31 de octubre de 2026. |
| Recibos del ciclo | `fecha_pago` desde el día **20** del mes anterior hasta el último día de `M`, y el recibo tiene al menos una factura creada en ese mes anterior. Noviembre 2026 = pagos del 20/10/2026 al 30/11/2026 ligados a una factura de octubre. |

La constante está en `CobrosMesVentana::DIA_INICIO_COBROS = 20`.

`mesesReferenciaAfectadosPorFactura` devuelve un solo mes: el mes siguiente al `created_at` de la factura (`Y-m-01`).

Ejemplo: factura creada el 20/10/2026 → ciclo **2026-11**. Factura creada el 06/11/2026 → ciclo **2026-12**.

---

## Saldo de una factura

Fórmula de `FacturaInterna::sqlSaldoPendienteExpr` y del atributo `saldo_pendiente`:

```
saldo = max(0, total − min(total, cobros aplicados) − notas de crédito)
```

- Los cobros aplicados son la suma de `cobro_factura_interna.monto` de esa factura.
- El tope `min(total, cobros)` evita que un pago de más deje el saldo negativo.
- Una nota de crédito resta del saldo y también del facturado del ciclo.
- Estados usados en el sistema: `pendiente`, `emitida`, `pagada`, `cancelada`, `anulada`.

El pendiente del mes solo suma facturas en estado `pendiente` o `emitida` con saldo mayor a cero. Una pagada, cancelada o anulada no entra, aunque su total siga dentro del facturado.

---

## Facturado, cobrado y pendiente

Los tres números del dashboard **no** se calculan uno a partir del otro. `pendiente` no es `facturado − cobrado`.

### Facturado

`CobrosMesVentana::calcularTotalFacturadoMesReferencia`

```
facturado = suma del total de las facturas del ciclo − suma de sus notas de crédito
```

Entran todas las facturas de esa ventana de creación, pagadas o no. No filtra por estado.

### Cobrado

`CobrosMesVentana::sumMontoRecibosCiclo`

```
cobrado = suma de cobros.monto
          donde fecha_pago está en la ventana del ciclo
          y el recibo aplica al menos una factura creada en el mes anterior
```

Se suma el **recibo entero**, no el trozo aplicado a la factura del ciclo. Si un recibo de 200.000 paga 100.000 de una factura de octubre y 100.000 de una de septiembre, noviembre suma 200.000.

Esa misma suma es la del cartel «Cobros del mes», la del total verde cuando las fechas son las del ciclo, y la del PDF de resumen con esos filtros.

### Pendiente

`CobrosMesVentana::calcularTotalPendienteMesReferencia`

```
pendiente = suma del saldo de las facturas del ciclo
            que siguen en estado pendiente o emitida
            y cuyo saldo es mayor a cero
```

En Cobros y recibos, «Pendientes del mes» parte ese saldo en vencidas y por vencer (`totalesPendientePorVencimiento`). El cartel muestra las vencidas. «Por vencer» es la otra parte y no se resta del cartel.

### Por qué no cierra la resta

| El facturado suma | El cobrado suma | El pendiente suma |
|---|---|---|
| El total de la factura | El recibo completo | Solo lo que esa factura todavía debe |
| Incluye pagadas, canceladas y anuladas | Puede incluir saldo a favor y facturas de otro mes en el mismo recibo | Excluye pagadas, canceladas y anuladas |
| Resta notas de crédito | No resta notas de crédito | Resta notas y cobros aplicados, con tope en el total |

Un recibo de 150.000 que aplica 100.000 a la factura del ciclo sube el cobrado en 150.000 y baja el pendiente solo en 100.000.

### Adelantado y atrasado

`clasificarLineaPivot` sigue usándose para `pago_adelantado` y `pago_atrasado`. **Ya no** define el `total_cobrado` del dashboard.

- Factura del ciclo y pago dentro de la ventana: baja el pendiente guardado por el monto del pivote.
- Factura anterior al ciclo, pagada desde el día 20: se anota como atrasado.
- Factura especial (`servicio_especial`): el clasificador la manda al mes calendario del pago y no baja el pendiente del ciclo. El cobrado del ciclo igual la incluye si la factura fue creada el mes anterior y el pago cae en la ventana.

---

## Qué se guarda y qué se recalcula

Tabla `cobros_resumen`, una fila por mes (`mes` = primer día, `Y-m-01`):

| Columna | Quién la escribe | Qué no toca |
|---|---|---|
| `total_facturado` | `aplicarImpactoFactura` / `recalcularFacturacionMes`. Se dispara al borrar la factura y al emitir nota de crédito. | Un cobro nuevo no la mueve. |
| `total_pendiente` | Lo mismo, recálculo completo del ciclo. Además, cada cobro la sube o la baja por el monto clasificado (`reduccion_pendiente`). | El recálculo de cobrado no la pisa. |
| `total_cobrado` | `escribirTotalCobrado`: vuelve a sumar los recibos del ciclo. Ocurre al registrar un cobro y, después de borrar el recibo, en `eliminarCobro`. | No modifica facturado ni pendiente. |
| `pago_adelantado`, `pago_atrasado` | Ajuste incremental en `aplicarImpactoCobro`. | El cobrado nuevo no los recalcula. |

`recalcularMes` reescribe facturado, pendiente **y** cobrado del mes. Sirve para auditar. No conviene correrlo sobre un mes cuyo facturado se corrigió a mano: lo reemplaza por la suma viva de las facturas que siguen en la base.

Comando de contraste, sin escribir: `php artisan cobros:auditar-resumen`.

---

## Alta, edición, nota de crédito y borrado

### Factura automática

`facturas:crear-internas-automaticas`, el día `dia_creacion_factura_automatica` a las 01:00.

- Clientes activos con al menos un servicio.
- Líneas de servicios activos, suspendidos o cortados. No entran los cancelados.
- Omite servicios con acuerdo de no facturar en ese período.
- Una línea por servicio, al precio del plan.
- El alta pasa por `aplicarImpactoFactura` y deja facturado y pendiente del ciclo siguiente alineados con las facturas vivas.

### Editar una factura

`FacturaInternaController::update` cambia totales, estado y, si corresponde, la forma o la fecha del último cobro. **No** llama a `refrescarResumenMesDeFactura`. Si se baja el total de 300.000 a 150.000, el facturado guardado del ciclo sigue con los 300.000 hasta un borrado, una nota de crédito o un `recalcularMes` de ese mes.

### Nota de crédito

`FacturacionService::emitirNotaCredito`

- Solo en facturas `pendiente` o `emitida`.
- El monto no puede pasar el saldo.
- Graba `factura_interna_notas_credito`.
- Si el saldo queda en cero, la factura pasa a `pagada`.
- Dentro de la misma transacción recalcula facturado y pendiente del ciclo. El cobrado no cambia: la nota no es un recibo.

### Borrar una factura

`FacturaInternaController::destroy`

1. Borra los cobros ligados con `cobros()->delete()`. Eso **no** dispara `eliminarCobro`, así que el `total_cobrado` guardado no se rearma.
2. Borra detalles y la factura. Las notas de crédito caen por cascada.
3. Revisa el estado de pago de los servicios.
4. `refrescarResumenMesDeFactura` recalcula facturado y pendiente del ciclo con las facturas que quedan.

Si la factura borrada estaba pendiente y sin cobros, el paso 1 no mueve dinero cobrado. El facturado y el pendiente sí bajan, porque el recálculo ya no la ve.

### Borrar un recibo

`FacturacionService::eliminarCobro` ajusta pendiente, adelantado y atrasado, borra el cobro y después vuelve a sumar `total_cobrado` de los meses cuya ventana incluía esa fecha de pago.

---

## Cobros y recibos

Un cobro (`cobros`) es el recibo. Puede aplicar a varias facturas internas por `cobro_factura_interna.monto`.

- `cobros.monto` es lo que pagó el cliente.
- La suma del pivote es lo aplicado a facturas.
- `cobros.monto − suma del pivote`, si es positivo, es saldo a favor.

Formas de pago: efectivo, transferencia, tarjeta, cheque, otro.

Filtros de `/cobros` que comparten el listado, el total verde, el PDF y el Excel:

- Usuario (si es administrador), cliente, fechas de pago, forma de pago, número de recibo.
- **Monto desde** y **Monto hasta** sobre `cobros.monto`. Acepta `150000` y `150.000`. El mismo valor en los dos campos deja solo ese monto.
- `factura_desde` y `factura_hasta` quedan ocultos cuando se abre desde el cartel del ciclo. Limitan a facturas creadas en ese rango.

El cartel «Cobros del mes» no usa las fechas que el usuario escriba después. Siempre es la ventana del ciclo, más los filtros de usuario, cliente, forma, recibo y monto.

Cobros (`/cobros/servicios`) no filtra por monto. Busca por cédula o nombre para registrar el pago.

---

## Tareas automáticas y comandos

| Comando | Para qué |
|---|---|
| `facturas:crear-internas-automaticas` | Emisión mensual. `--dry-run` no graba. `--force` ignora el día configurado. |
| `servicios:corte-automatico` | Suspensión por falta de pago, en el día y la hora de configuración. |
| `cobros:auditar-resumen` | Compara `cobros_resumen` con el cálculo vivo. `--fix` reescribe los meses con diferencia. |
| `cobros:auditar-dashboard-inicio` | Contrasta el inicio y Cobros y recibos en la misma ventana. |
| `cobros:auditar-saldo-favor-recibos` | Recibos con parte no aplicada. |
| `cobros:auditar-monto-pagado-facturas` | Cobros aplicados contra el total de la factura. |
| `facturacion:exportar-excel` | Libro `.xlsx` para análisis. |

`--fix` y `recalcularMes` reescriben facturado y pendiente. Usarlos en un mes corregido a mano borra esa corrección.

---

## Cómo exportar para analizar

En el proyecto:

```bat
php artisan facturacion:exportar-excel
```

Con rango:

```bat
php artisan facturacion:exportar-excel --desde=2026-10-01 --hasta=2026-11-30
```

Las facturas se filtran por `created_at`. Los recibos y la hoja de aplicaciones, por `fecha_pago`. El archivo queda en `storage/app/exportaciones/fecha/facturacion.xlsx`.

| Hoja | Una fila es |
|---|---|
| Facturas | Una factura: cliente, fechas, total, cobrado aplicado, notas, saldo y **mes ciclo**. |
| Detalle | Una línea de la factura. |
| Notas de credito | Una nota. |
| Recibos | Un recibo: monto, aplicado y saldo a favor. |
| Recibos y facturas | Cuánto de cada recibo se aplicó a cada factura. |

**Mes ciclo** es el mes del dashboard: el mes siguiente a la creación de la factura. Los montos van como número, para poder sumarlos.

Para repetir el cartel de noviembre 2026 en una tabla dinámica: hoja Facturas, mes ciclo `2026-11`. El cobrado del cartel no sale de esa hoja; sale de Recibos con pago entre el 20/10/2026 y el 30/11/2026 que en Recibos y facturas toquen una factura creada en octubre.

---

## Dónde el número se puede desviar

1. **Editar el total no refresca el resumen.** El listado vivo baja. El facturado guardado no, hasta otro recálculo del ciclo.
2. **Borrar una factura con cobros no rearma el cobrado.** Los recibos se borran sin `eliminarCobro`. Facturado y pendiente sí se recalculan.
3. **El pendiente guardado se mueve de dos maneras.** Al cobrar, baja por el monto clasificado. Al borrar o anotar crédito, se reemplaza por la suma viva. Si el clasificador y el saldo no coinciden, el pendiente guardado se corre hasta el próximo recálculo de facturación.
4. **Un recibo puede entrar entero en dos meses.** Del 20 al último día del mes la ventana del mes en curso y la del mes siguiente se pisan. Un pago del 25/10 que toca una factura de septiembre y otra de octubre suma el recibo completo en octubre y otra vez en noviembre.
5. **Cobrado y pendiente no usan el mismo pedazo del recibo.** Por eso el dashboard no cierra `facturado − cobrado`.
6. **`recalcularMes` pisa el facturado guardado** con la suma de las facturas que existen en ese momento, notas incluidas.

---

## Mejoras concretas

Orden sugerido según el efecto en los números que ya se miran.

1. Al guardar la edición de una factura interna, llamar a `refrescarResumenMesDeFactura` igual que en la nota de crédito. Hoy un cambio de total queda solo en la factura.
2. Al borrar una factura, resincronizar `total_cobrado` de las fechas de pago de los recibos que se van con ella, antes o después del `delete`. Hoy ese cobrado queda hasta el próximo recibo que toque el mismo mes.
3. Decidir una sola regla para el pendiente guardado: o se recalcula siempre desde el saldo vivo, o se mantiene el ajuste incremental. Las dos juntas son la fuente del desvío.
4. Decidir si un recibo que cae en dos ventanas se cuenta entero en los dos meses o solo la parte del pivote de cada ciclo. Hoy el cartel y el PDF cuentan el recibo entero, y por eso se pidió igualar el dashboard a esa suma.
5. Documentar en el propio dashboard la frase del ciclo (facturas del mes anterior, cobros desde el día 20) para no comparar ese cobrado con un filtro de fechas distinto.
6. Antes de cualquier `--fix` o `recalcularMes` sobre un mes ya corregido, anotar el facturado guardado. El comando lo va a reemplazar.

No hace falta tocar el facturado de meses cerrados para analizar. La hoja Facturas del Excel, filtrada por mes ciclo, es la suma viva. La columna Facturado del dashboard es el valor guardado. La diferencia entre las dos es el desvío a revisar.
