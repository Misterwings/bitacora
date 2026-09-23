# Manual de usuario y administración

## 1. Objetivo

Este manual explica cómo utilizar Bitácora Mister Wings para:

- Iniciar y cerrar sesión.
- Diligenciar una bitácora operativa.
- Registrar un reporte de supervisión.
- Guardar, restaurar y resolver conflictos de borradores.
- Generar un PDF sin enviarlo.
- Enviar la bitácora al correo configurado.
- Administrar campos y destinatarios cuando se tiene rol `admin`.

La pantalla muestra únicamente los campos que corresponden a la empresa, sede, tipo de formulario y configuración vigente. Por esa razón, dos usuarios pueden ver secciones diferentes.

## 2. Roles

### Usuario normal

- Accede al formulario de la empresa asignada.
- Solo puede registrar información para su empresa.
- Puede crear y administrar sus propios borradores.
- Puede generar PDF y enviar reportes.
- No puede modificar campos, sedes ni destinatarios.

### Administrador

- Tiene las capacidades del usuario normal.
- Puede seleccionar una empresa configurada desde **Empresa activa**.
- Puede abrir **Administrar formulario**.
- Puede abrir **Parametrizar correos**.
- Puede administrar campos y destinatarios dentro de las empresas disponibles.
- Sus cambios administrativos quedan registrados en auditoría.

## 3. Iniciar sesión

1. Abra la dirección de la aplicación.
2. Escriba el **Usuario**.
3. Escriba la **Contraseña**.
4. Pulse **Ingresar**.
5. Espere la redirección a la bitácora asignada.

Si las credenciales son incorrectas, revise que no haya espacios adicionales y vuelva a intentarlo. Después de varios intentos fallidos, el acceso puede quedar bloqueado temporalmente para proteger la cuenta.

### 3.1 Sesión por expirar

La aplicación controla la inactividad y la duración máxima de la sesión. Antes de cerrar la sesión aparece una ventana con una cuenta regresiva.

- **Continuar sesión**: valida y renueva la sesión de forma explícita.
- **Ir al login**: cierra el flujo actual y vuelve a la pantalla de acceso.

Si la sesión expira mientras se diligencia el formulario, el servidor rechazará el envío. Renueve la sesión antes de continuar y confirme que el borrador quedó guardado.

## 4. Pantalla principal

La pantalla de bitácora contiene:

- Barra superior con logo, título, usuario y cierre de sesión.
- Selector **Empresa activa**, visible para administradores.
- Accesos administrativos, visibles solo para administradores.
- Barra de progreso del registro.
- Barra de estado del borrador.
- Navegación por secciones.
- Formulario con campos básicos y secciones condicionales.
- Acciones inferiores.

Las acciones de un formulario operativo son:

- **Cargar/Restaurar**: recuperar el borrador del servidor.
- **Eliminar borrador**: eliminar el borrador guardado en el servidor.
- **Guardar ahora**: guardar inmediatamente.
- **Generar PDF**: preparar y descargar el PDF sin enviar correo.
- **Enviar bitácora**: validar, generar el reporte y enviarlo.

Las acciones de un formulario de supervisión son iguales, excepto que el botón principal dice **Enviar reporte** y no se genera PDF en el flujo de supervisión.

## 5. Diligenciar una bitácora operativa

### 5.1 Datos básicos

Complete como mínimo:

1. **Fecha de bitácora**.
2. **Sede**.
3. **Responsable**.
4. **Cargo**.

La fecha se prellena con el día actual. El responsable puede prellenarse con el nombre del usuario conectado. Revise siempre los valores antes de enviar.

### 5.2 Secciones y progreso

La barra de progreso indica cuántos campos obligatorios visibles están completos. Cada sección muestra un estado:

- Pendiente.
- Parcial.
- Completa.
- Sin campos obligatorios.

Pulse una sección en la navegación para desplazarse hasta ella. Un campo oculto por sede no se cuenta como pendiente y no se envía.

### 5.3 Campos dependientes

Algunas preguntas muestran información adicional solo después de una respuesta:

- Si responde **Sí**, aparece un detalle que debe diligenciarse.
- Si responde **No**, el detalle puede ocultarse o recibir un texto predeterminado.
- En grupos con cantidad, la cantidad determina cuántos registros aparecen.
- En grupos con ramas, solo se muestra la rama correspondiente a **Sí** o **No**.
- En listas de visitantes, cada persona seleccionada puede solicitar hora de entrada, hora de salida y actividades.

No intente diligenciar campos que estén deshabilitados u ocultos. La aplicación los excluye del envío y el servidor vuelve a validar esta regla.

### 5.4 Campos numéricos

Ingrese solamente números en los campos numéricos. Algunos campos muestran:

- Porcentaje.
- Pesos colombianos.
- Unidades, bolsas, horas u otros sufijos.
- Límites mínimo y máximo.

El formato de presentación del PDF puede diferir del valor escrito en pantalla. Por ejemplo, un valor monetario puede aparecer con `$`, puntos de miles y coma decimal.

### 5.5 Visitantes y registros repetibles

Cuando un bloque solicita varias personas o registros:

1. Seleccione la cantidad o las personas.
2. Espere a que aparezcan los subcampos.
3. Complete todos los subcampos obligatorios de cada registro visible.
4. Si no hubo actividad, seleccione la opción de no aplicación cuando exista.

No seleccione simultáneamente **No aplica visita** y otras personas. La aplicación conservará la opción de no aplicación y eliminará los detalles incompatibles.

## 6. Borradores

Los cambios se guardan automáticamente en el servidor aproximadamente cinco segundos después de una modificación. También puede pulsar **Guardar ahora**.

El borrador:

- Pertenece al usuario, empresa y tipo de formulario actuales.
- Se guarda cifrado en el servidor.
- Se conserva durante el periodo configurado por la organización.
- No depende del almacenamiento local del navegador.
- Se elimina normalmente después de un envío aceptado.

### 6.1 Indicadores del borrador

La barra puede mostrar:

- **Buscando**: consultando si existe un borrador.
- **Guardado**: no hay cambios pendientes.
- **Cambios**: hay cambios que se guardarán automáticamente.
- **Guardando**: se está enviando el cambio al servidor.
- **Sin conexión**: el navegador no puede contactar el servidor; los cambios siguen pendientes.
- **Conflicto**: existe una versión más reciente en el servidor.
- **Finalizado**: el envío aceptado eliminó el borrador.

No cierre la pestaña si aparece **Cambios** o **Guardando** y necesita conservar la información. Si debe cerrar el navegador, pulse **Guardar ahora** primero.

### 6.2 Borrador existente al abrir el formulario

Si el servidor encuentra un borrador, la aplicación ofrece:

- **Restaurar**: cargar la información guardada.
- **Empezar en blanco**: dejar el formulario vacío, conservando el borrador para una restauración posterior.
- **Descartar**: eliminar el borrador.

Si el formulario cambió desde el último guardado, la ventana informa que algunos campos podrían no restaurarse. Revise el formulario completo antes de enviar.

### 6.3 Conflicto de borrador

Un conflicto ocurre cuando otra pestaña, otra solicitud o una sesión paralela guardó una versión más reciente.

1. Detenga el diligenciamiento momentáneamente.
2. Pulse **Cargar servidor** para reemplazar sus cambios por la versión guardada.
3. Si está seguro de que sus cambios son los correctos, pulse **Sobrescribir**.
4. Revise la barra de estado y guarde nuevamente.

No pulse **Sobrescribir** sin confirmar con la persona que pudo modificar el borrador. El sistema usa control de versiones para evitar pérdidas silenciosas.

## 7. Generar un PDF

Para generar un PDF sin enviar la bitácora:

1. Complete los campos requeridos.
2. Guarde el borrador o espere a que el guardado automático termine.
3. Pulse **Generar PDF**.
4. Espere el mensaje **PDF generado**.
5. El navegador iniciará la descarga automáticamente.

Generar el PDF no envía correo ni finaliza el borrador. Puede continuar editando y volver a generar otro PDF.

Si el PDF no se genera, el mensaje mostrará una advertencia. Revise el formulario y vuelva a intentarlo; si el problema persiste, informe la fecha, sede y mensaje exacto al soporte.

## 8. Enviar una bitácora

### 8.1 Envío operativo

1. Complete todas las secciones visibles.
2. Revise la barra de progreso.
3. Pulse **Enviar bitácora**.
4. En la ventana de revisión, confirme que las secciones estén completas.
5. Pulse **Enviar**.
6. Espere el resultado final.

Durante el proceso no cierre la pestaña ni pulse varias veces el botón. El botón se deshabilita y el sistema conserva una referencia idempotente para evitar duplicados.

El resultado puede informar por separado:

- **Correo: Enviado**.
- **Correo: En cola**.
- **Correo: No enviado**.
- **PDF: Generado**.
- **PDF: No generado**.
- Advertencias específicas.

Un envío asíncrono puede mostrar **En cola** aunque todavía no haya llegado al destinatario. El worker lo procesará posteriormente.

### 8.2 Envío de supervisión

El reporte de supervisión solicita fecha, horario, sede, área, responsable, hallazgos, retroalimentación, tareas, plan de acción y otras actividades.

1. Complete **Información de supervisión**.
2. Pulse **Enviar reporte**.
3. Confirme el resumen.
4. Espere el mensaje de correo enviado o encolado.

El reporte de supervisión no genera PDF en el flujo actual.

### 8.3 Si el envío falla

- Si se generó el PDF pero no se envió el correo, conserve el PDF descargado y no repita inmediatamente el envío sin revisar el estado.
- Si aparece una entrega incierta, consulte con soporte o el proveedor SMTP antes de repetir. El correo pudo haber sido aceptado aunque la pantalla mostrara un error.
- Si el borrador se conserva, significa que no se pudo confirmar correctamente su finalización o que existe una versión más reciente.
- No borre el borrador hasta confirmar que el reporte quedó registrado.

## 9. Cerrar sesión

Pulse **Cerrar sesión** en la barra superior. Use siempre esta acción en equipos compartidos.

Cerrar la pestaña no equivale a cerrar sesión. La sesión puede permanecer activa hasta su vencimiento configurado.

## 10. Administración de formularios

Esta sección es exclusiva para administradores.

### 10.1 Seleccionar empresa

1. Abra **Administrar formulario** desde la bitácora.
2. Seleccione la empresa en el selector superior.
3. Espere a que se cargue la configuración correspondiente.

Compruebe la empresa antes de guardar. Cada cambio se aplica a esa empresa, no necesariamente a todas.

### 10.2 Pestañas disponibles

El panel contiene:

- **Campos dinámicos**: elementos agregados desde configuración.
- **Campos base**: elementos definidos por el esquema PHP y permitidos para edición.
- **Visibilidad**: ocultar o mostrar campos base permitidos.

También existe **Vista previa** para revisar cómo se renderiza el formulario.

### 10.3 Agregar un campo dinámico

1. Abra la pestaña **Campos dinámicos**.
2. Pulse **+ Agregar elemento**.
3. Seleccione el **Tipo**.
4. Escriba un **Identificador técnico** único.
5. Escriba la **Etiqueta visible**.
6. Seleccione o escriba la **Sección**.
7. Defina el **Orden**.
8. Marque **Campo obligatorio** solo si el usuario debe responderlo.
9. Seleccione las **Sedes donde aparece**, o ninguna para todas.
10. Complete las opciones específicas del tipo.
11. Pulse **Agregar campo**.
12. Use **Vista previa** y pruebe el formulario con una sede real.

Tipos disponibles en la interfaz:

- Texto corto.
- Texto largo.
- Número.
- Fecha.
- Hora.
- Lista.
- Lista múltiple.
- Sí / No con detalle.
- Sí / No con cantidad y registros.
- Cantidad y registros.
- Lista con detalle por persona.
- Etiqueta de subsección.

### 10.4 Reglas de identificadores

Un identificador técnico válido:

- Comienza por una letra.
- Usa solo letras, números y `_`.
- No contiene espacios, tildes ni guiones.
- No repite nombres de campos base, dinámicos ni subcampos.

Ejemplos válidos: `temperatura_nevera`, `visitas_proveedores`.

Ejemplos inválidos: `temperatura nevera`, `1_temperatura`, `temperatura-nevera`.

La etiqueta visible sí puede contener espacios, mayúsculas, tildes e instrucciones para el usuario.

### 10.5 Configurar campos especiales

#### Número

Puede definir:

- Mínimo y máximo.
- Paso.
- Sufijo.
- Formato normal o moneda.
- Número de decimales, entre 0 y 6.
- Sufijo singular para el valor 1.
- Sufijo plural para otros valores.

#### Sí/No con detalle

Defina:

- Nombre técnico del detalle.
- Etiqueta del detalle.
- Tipo de detalle: texto largo, número, fecha o lista múltiple.
- Opciones si el detalle es una lista múltiple.
- Texto de referencia para respuestas negativas.

El detalle se muestra cuando se selecciona **Sí**.

#### Sí/No con cantidad y registros

Defina:

- Nombre y etiqueta de la cantidad.
- Mínimo y máximo de registros, hasta 10.
- Etiqueta de cada registro.
- Subcampos de cada registro.
- Texto para una respuesta negativa.
- Sufijos de cantidad.

#### Cantidad y registros

Este tipo no pregunta Sí/No. La cantidad puede ser cero y, cuando es cero, los registros no se muestran en el reporte.

#### Lista con detalle por persona

Configure:

- Opciones de la lista.
- Nombre técnico del detalle.
- Texto de no aplicación.
- Texto guía y ayuda visible.

La selección de una persona crea automáticamente los campos de hora de ingreso, hora de salida y actividades.

### 10.6 Editar, duplicar, ordenar y eliminar

- **Editar**: modifica la configuración del campo.
- **Duplicar**: crea una copia con identificadores nuevos.
- **Subir/Bajar**: cambia el orden dentro de la lista.
- **Eliminar**: elimina el campo dinámico de la configuración.
- **Vista previa**: muestra el formulario resultante.

Antes de eliminar o cambiar un campo que ya se usa, revise si existen borradores activos. Un cambio de esquema puede generar una advertencia y omitir datos incompatibles al restaurar.

### 10.7 Campos base y visibilidad

Los campos base pueden permitir cambios de etiqueta, obligatoriedad, ancho, opciones, formato y sedes. Algunos campos son protegidos y no se pueden ocultar:

- `fechab`.
- `fechasup`.
- `sede`.
- `responsable`.
- `responsableb`.
- `cargo`.

Para ocultar un campo permitido:

1. Abra **Visibilidad**.
2. Marque los campos que no deben mostrarse.
3. Pulse **Guardar campos ocultos**.
4. Revise la vista previa.

No oculte un campo sin confirmar que no sea necesario para validación, PDF, correo o auditoría operativa.

## 11. Administración de destinatarios

### 11.1 Abrir el panel

1. Desde la bitácora, pulse **Parametrizar correos**.
2. Seleccione la empresa.
3. Seleccione el **Alcance de la lista**:
   - Global de la empresa.
   - Una sede específica.
4. Revise **Fuente actual** antes de modificar.

Los cambios afectan nuevos envíos. No modifican correos que ya fueron enviados o que ya están almacenados en una cola.

### 11.2 Correo completo


- Agregar un correo.
- Editar un correo.
- Elegir tipo `Para`, `CC` o `CCO`.
- Definir alcance global o por sede.
- Activar o desactivar el registro.
- Subir o bajar su orden.
- Consultar la vista efectiva para la sede seleccionada.

En la pestaña **Correo completo** se administran los destinatarios del correo principal. El tipo seleccionado determina si el correo aparece en **Para**, **CC** o **CCO**.

### 11.3 Destinatarios por sección

La pestaña **Por sección** está disponible para empresas operativas. Permite enviar únicamente determinadas secciones a un destinatario.

1. Escriba el correo.




2. Seleccione `Para`, `CC` o `CCO`.
3. Seleccione el alcance global o una sede.
4. Seleccione la sección del formulario.
5. Marque **Activo**.
6. Pulse **Agregar asignación** o **Guardar cambios**.


- Recibe únicamente las secciones asignadas.
- No recibe el correo completo.
- No recibe el PDF adjunto.
- Puede tener asignaciones para una empresa completa o una sede.

### 11.4 Vista previa y precedencia

La sección **Vista efectiva** muestra los destinatarios que utilizará el próximo envío para la sede seleccionada:

1. Revise el alcance.



- Para.
- CC.
- CCO.



2. Revise el tipo.
3. Revise que el registro esté activo.
4. Consulte la vista efectiva.
5. Haga una prueba controlada, preferiblemente en un entorno de desarrollo SMTP.

## 12. Pruebas operativas recomendadas

### Usuario



- Abrir la bitácora correcta.
- Cambiar de sede y confirmar que los campos condicionales cambien.
- Completar un caso mínimo y guardar.
- Cerrar y reabrir para restaurar el borrador.
- Generar PDF.
- Enviar una bitácora de prueba.
- Confirmar correo, PDF y limpieza del borrador.

### Administrador


- Revisar sedes activas.
- Verificar la vista previa del formulario.
- Agregar y quitar un campo de prueba.
- Revisar que el cambio aparezca en la sede esperada.
- Revisar destinatarios globales y por sede.
- Revisar la vista efectiva `Para`, `CC` y `CCO`.
- Confirmar que una asignación por sección no reciba secciones no asignadas.

### Prueba SMTP local

En desarrollo normal, el correo se captura en Mailpit. Desde la raíz del proyecto:



```bash
docker compose exec app php scripts/smoke_mail.php
```

Para SMTP real de desarrollo, seguir el procedimiento de `README.md` y usar un destinatario de prueba autorizado.


## 13. Problemas frecuentes

| Problema | Qué revisar |
| --- | --- |
| El login no responde | Conexión a la aplicación, sesión, CSRF y logs de `app` |
| Demasiados intentos | Esperar el bloqueo temporal o solicitar soporte |
| No aparece una empresa | La cuenta no tiene rol admin o la empresa no está configurada |
| No aparece un campo | Sede seleccionada, visibilidad, alcance del campo o configuración vigente |
| El progreso no llega a 100% | Revisar las secciones visibles y los campos obligatorios |
| El borrador no carga | Sesión, conectividad, expiración o cambio de esquema |
| Aparece conflicto | Elegir **Cargar servidor** o **Sobrescribir** conscientemente |
| El PDF no descarga | Verificar sesión, expiración del enlace y almacenamiento |
| El correo queda en cola | El worker todavía debe procesarlo; soporte debe revisar la cola |
| El correo no llega | Revisar destinatarios, estado del envío, SMTP y Mailpit/proveedor |
| La sesión se cerró | Renovarla desde la ventana de timeout y revisar el borrador |
| La pantalla muestra 504 | Es un problema de proxy; producción debe apuntar a Nginx, no a PHP-FPM |

Al solicitar soporte, incluya:

- Usuario y empresa, nunca la contraseña.
- Fecha y hora aproximada.
- Sede y tipo de formulario.
- Acción realizada: guardar, PDF o envío.
- Mensaje exacto mostrado.
- Si el correo aparecía enviado, en cola o no enviado.

No envíe tokens de PDF, claves de borrador, contraseñas ni archivos `.env` por canales no autorizados.

## 14. Buenas prácticas

- Diligenciar y enviar el reporte durante el turno correspondiente.
- Revisar fecha, sede, responsable y cargo antes de llenar el resto.
- Guardar manualmente antes de cambiar de pestaña o cerrar el navegador.
- Resolver conflictos de borrador antes de seguir editando.
- No repetir un envío con estado incierto sin verificarlo.
- No compartir una cuenta entre varias personas.
- Cerrar sesión en equipos compartidos.
- Usar la vista previa después de cambios administrativos.
- Probar cambios de destinatarios antes de usarlos en producción.
- Mantener separados los entornos de prueba y producción.
