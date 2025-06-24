<?php


// Autenticación y obtención de datos del usuario
if (isset($_COOKIE['user_session'])) {
    $user_data = json_decode(base64_decode($_COOKIE['user_session']), true);

    if ($user_data) {
        $user_id = $user_data['user_id'];
        $username = $user_data['username'];
        $role = $user_data['role'];
        $isAdmin = ($role === 'admin');
    } else {
        header("Location: login.php");
        exit;
    }
} else {
    header("Location: login.php");
    exit;
}

include('header.php')
    ?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <title>Manual de Usuario CRM</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            overflow-x: hidden;
        }

        #menu {
            height: 100vh;
            overflow-y: auto;
            border-right: 1px solid #ccc;
        }

        .contenido {
            scroll-margin-top: 80px;
        }

        #buscador {
            margin-bottom: 10px;
        }

        .nav-link {
            padding-left: 1rem;
        }

        .nav-sub {
            padding-left: 2rem;
            font-size: 0.9rem;
        }

        .justificado {
            text-align: justify;
            text-justify: inter-word;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;

        }

        .párrafo-sangrado-total {
            margin-left: 2em;
            text-align: justify;
        }

        p {
            text-align: justify;
            margin-left: 2rem;
            padding-left: 1rem;
            border-left: 3px solid #d0d0d0;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1rem;
        }





        .manual-img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin: 15px auto;
            display: block;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Índice lateral -->
            <nav id="menu" class="col-md-3 col-sm-4 bg-light p-3">
                <input type="text" id="buscador" class="form-control" placeholder="Buscar sección...">
                <ul id="indice" class="nav flex-column mt-3">
                    <li><a class="nav-link" href="#introduccion">1. Introducción</a></li>
                    <li><a class="nav-link nav-sub" href="#seguridad">1.1 Seguridad</a></li>
                    <li><a class="nav-link nav-sub" href="#soporte">1.2 Soporte</a></li>

                    <li><a class="nav-link" href="#ventas">2. Módulo de ventas</a></li>
                    <li><a class="nav-link nav-sub" href="#registro_ventas">2.1 Registro de ventas</a></li>
                    <li><a class="nav-link nav-sub" href="#acceso_ventas">- Acceso</a></li>
                    <li><a class="nav-link nav-sub" href="#campos_ventas">- Campos a llenar</a></li>
                    <li><a class="nav-link nav-sub" href="#envio_ventas">- Venta y envío de registro</a></li>
                    <li><a class="nav-link nav-sub" href="#visualizacion_ventas">2.2 Visualización/edición</a></li>
                    <li><a class="nav-link nav-sub" href="#reglas_ventas">2.3 Reglas de registros</a></li>
                    <li><a class="nav-link nav-sub" href="#no_dobles">- No se permite registros dobles</a></li>
                    <li><a class="nav-link nav-sub" href="#especiales">- Ingresar registros dobles especiales</a></li>
                    <li><a class="nav-link nav-sub" href="#importante_precio">Importante sobre esta sección</a></li>
                    <li><a class="nav-link nav-sub" href="#anomalo">- Sobre precios anómalos</a></li>
                    <li><a class="nav-link nav-sub" href="#comisiones">2.4 Sub módulo de comisiones</a></li>
                    <li><a class="nav-link nav-sub" href="#acceso_comision">- Acceso</a></li>
                    <li><a class="nav-link nav-sub" href="#agregar_comision">- Agregar comisión</a></li>
                    <li><a class="nav-link nav-sub" href="#subir_comprobante">- Subir imagen/captura a drive</a></li>
                    <li><a class="nav-link nav-sub" href="#formulario_comision">- Llenar formulario</a></li>
                    <li><a class="nav-link nav-sub" href="#extra_comision">● Extra</a></li>
                    <li><a class="nav-link nav-sub" href="#ver_comisiones">- Visualización de registros</a></li>

                    <li><a class="nav-link" href="#reportes">3. Módulo de reportes</a></li>
                    <li><a class="nav-link nav-sub" href="#que_es_reporte">3.1 Qué es un reporte</a></li>
                    <li><a class="nav-link nav-sub" href="#ingresar_reporte">3.2 Ingresar un reporte</a></li>
                    <li><a class="nav-link nav-sub" href="#acceso_reporte">- Acceso</a></li>
                    <li><a class="nav-link nav-sub" href="#agregar_reporte">- Agregar un reporte</a></li>
                    <li><a class="nav-link nav-sub" href="#tipo_reporte">Tipo de reporte</a></li>
                    <li><a class="nav-link nav-sub" href="#problemas">Problemas</a></li>
                    <li><a class="nav-link nav-sub" href="#cursos_vendidos">Cursos más vendidos</a></li>
                    <li><a class="nav-link nav-sub" href="#dudas">Dudas frecuentes</a></li>
                    <li><a class="nav-link nav-sub" href="#clientes_potenciales">Clientes potenciales</a></li>
                    <li><a class="nav-link nav-sub" href="#ver_reportes">- Visualización de reportes</a></li>

                    <li><a class="nav-link" href="#seguimientos">4. Módulo de seguimientos</a></li>
                    <li><a class="nav-link nav-sub" href="#que_es_seguimiento">4.1 Para qué sirve</a></li>
                    <li><a class="nav-link nav-sub" href="#manejo_seguimiento">4.2 Manejo de seguimientos</a></li>
                    <li><a class="nav-link nav-sub" href="#acceso_seguimiento">- Acceso</a></li>
                    <li><a class="nav-link nav-sub" href="#seguimiento_cliente">- Hacer seguimiento</a></li>
                    <li><a class="nav-link nav-sub" href="#recordatorios">- Seguimiento con recordatorio</a></li>
                    <li><a class="nav-link nav-sub" href="#recordatorios_hoy">Mostrar recordatorios actuales/futuros</a>
                    </li>
                    <li><a class="nav-link nav-sub" href="#recordatorios_pasados">Mostrar recordatorios pasados</a></li>
                    <li><a class="nav-link nav-sub" href="#ver_todos">Ver todos</a></li>
                    <li><a class="nav-link nav-sub" href="#notificaciones">4.3 Desde notificaciones</a></li>
                    <li><a class="nav-link nav-sub" href="#hoy">- Recordatorios para hoy</a></li>
                    <li><a class="nav-link nav-sub" href="#vencidos">- Recordatorios vencidos</a></li>
                    <li><a class="nav-link nav-sub" href="#ver_recordatorios">- Ver recordatorios</a></li>

                    <li><a class="nav-link" href="#puntos">5. Sistema de puntos</a></li>
                    <li><a class="nav-link nav-sub" href="#semanales">5.1 Recompensas semanales</a></li>
                    <li><a class="nav-link nav-sub" href="#ganancia_semanal">- Ganancia de puntos</a></li>
                    <li><a class="nav-link nav-sub" href="#visualizar_catalogo">- Visualización y catálogo</a></li>
                    <li><a class="nav-link nav-sub" href="#mensuales">5.2 Recompensas mensuales</a></li>
                    <li><a class="nav-link nav-sub" href="#ganancia_mensual">- Ganancia de puntos</a></li>
                    <li><a class="nav-link nav-sub" href="#progreso_mensual">- Visualización de progreso</a></li>
                    <li><a class="nav-link nav-sub" href="#historicos">5.3 Recompensas históricas</a></li>
                    <li><a class="nav-link nav-sub" href="#ganancia_historica">- Ganancia de puntos</a></li>
                    <li><a class="nav-link nav-sub" href="#solicitar_actividad">Pasos para solicitar puntos por
                            actividad</a></li>
                    <li><a class="nav-link nav-sub" href="#ver_recompensas">5.4 Visualización de datos</a></li>
                    <li><a class="nav-link nav-sub" href="#ver_semanal_mensual">- Recompensas semanales y mensuales</a>
                    </li>
                    <li><a class="nav-link nav-sub" href="#ver_historicos">- Recompensas históricas</a></li>
                    <li><a class="nav-link nav-sub" href="#sanciones">EXTRA: Sanciones y bonus</a></li>

                    <li><a class="nav-link" href="#asistencias">6. Módulo de asistencias</a></li>
                    <li><a class="nav-link nav-sub" href="#acceso_asistencia">Acceso</a></li>
                    <li><a class="nav-link nav-sub" href="#ver_asistencias">Visualización de datos</a></li>
                    <li><a class="nav-link nav-sub" href="#minutos">Minutos pendientes</a></li>
                    <li><a class="nav-link nav-sub" href="#historial_asistencia">Historial de asistencia</a></li>
                </ul>
            </nav>

            <!-- Contenido -->
            <!-- Contenido -->
            <main class="col-md-9 col-sm-8 p-4">
                <h2 id="introduccion" class="contenido">1. Introducción</h2>
                <p class="justificado">
                    El sistema CRM permite a los usuarios gestionar las ventas, clientes y reportes de manera
                    centralizada, brindando herramientas intuitivas para optimizar el seguimiento de oportunidades y
                    cerrar ventas de forma efectiva.
                </p>

                <h3 id="seguridad" class="contenido ms-3">1.1 Seguridad</h3>
                <p class="justificado">
                    Se le asignará credenciales para el acceso: Usuario y contraseña que no podrá cambiar a menos de
                    solicitarlo a un administrador


                </p>
                <p class="justificado">
                    Está prohibido compartir esas credenciales.
                </p>


                <h3 id="soporte" class="contenido ms-3">1.2 Soporte</h3>
                <p class="justificado">
                    Si encuentra un bug o una falla, reportar lo antes posible para solucionarlo y/o mejorar el CRM.

                </p>
                <p class="justificado">
                    Si encuentra formas de mejorar el uso de este sistema, mencionarlo, porque si el sistema está para
                    facilitar sus actividades.

                </p>


                <h2 id="ventas" class="contenido">2. Módulo de Ventas (primordial)</h2>
                <h3 id="registro_ventas" class="contenido ms-3">2.1 Registro de ventas</h3>
                <h4 id="acceso_ventas" class="contenido ms-4">Acceso</h4>
                <figure>
                    <img src="img/accsesoventas.png" class="manual-img" alt="Vista del registro de ventas">
                    <figcaption class="text-center text-muted mt-2">Vista del formulario de ventas en el sistema CRM.
                    </figcaption>
                </figure>
                <h4 id="campos_ventas" class="contenido ms-4">Campos a llenar</h4>
                <p class="justificado">
                    <strong>Producto (obligatorio):</strong> Apartado donde se coloca el nombre del producto. El sistema
                    sugerirá según se vaya ingresando el producto
                </p>
                <img src="img/producto1.png" alt="Ejemplo del módulo de ventas" class="manual-img">
                <p class="justificado">
                    Si el producto no tiene canal designado como sufijo agregar manualmente
                </p>
                <img src="img/producto2.png" alt="Ejemplo del módulo de ventas" class="manual-img">
                <p class="justificado">
                    A modo que se llene este campo, se mostrará una lista de los últimos 5 registros con hora, nombre
                    producto, nombre/número, Moneda precio
                </p>
                <img src="img/producto3.png" alt="Ejemplo del módulo de ventas" class="manual-img">
                <p class="justificado">
                    Si el nombre coincide con una entrada ya definida en el sistema se sugerirá el tipo de moneda debajo
                    del campo.
                </p>
                <img src="img/producto4.png" alt="Ejemplo del módulo de ventas" class="manual-img">
                <br>
                <p class="justificado">
                    <strong>Precio (obligatorio):</strong> Apartado donde se coloca el precio del producto a la hora de
                    la venta
                </p>
                <img src="img/precio1.png" alt="Ejemplo precio" class="manual-img">
                <p class="justificado">
                    Si el nombre del producto está registrado en el sistema se sugería el precio base y las posibles
                    variantes según se cierre de la venta
                </p>
                <img src="img/precio2.png" alt="Ejemplo precio" class="manual-img">
                <p class="justificado">
                    <strong>Teléfono/nombre (obligatorio):</strong> Campo donde se agrega un identificador del cliente,
                    puede ser Número
                    de whatsapp o nombre de facebook
                </p>
                <img src="img/telefono.png" alt="Ejemplo venta" class="manual-img">
                <p class="justificado">
                    <strong>Switches de selección (dependiente):</strong> Dos switches que
                    sirven para cambiar tipo de venta ( whatsapp o Messenger) también el campo de Teléfono/nombre.
                    También para agregar observaciones de la venta (puede ser desde un apunte hasta un descuento)
                </p>
                <img src="img/switches1.png" alt="Ejemplo venta" class="manual-img">
                <p class="justificado">
                    Por defecto estará en messenger y las observaciones estarán pegadas
                </p>
                <img src="img/switches2.png" alt="Ejemplo venta" class="manual-img">
                <p class="justificado">
                    <strong>Cantidad (obligatorio):</strong> Apartado donde se coloca la cantidad del producto vendido
                    (por defecto 1)
                </p>
                <img src="img/cantidad.png" alt="Ejemplo venta" class="manual-img">
                <p class="justificado">
                    <strong> Observaciones (opcional):</strong> Si el registro tiene algo que
                    explicar se activa el switch para activar el campo Observaciones donde se llena lo que se requiera
                </p>
                <img src="img/obsevaciones.png" alt="Ejemplo venta" class="manual-img">
                <h4 id="envio_ventas" class="contenido ms-4">Venta y envío de registro</h4>
                <p class="justificado">
                    <strong>Registro de venta enviado:</strong> Existen 3 botones para enviar
                    según moneda corresponda. Las principales son PEN y MXN
                </p>
                <img src="img/registro.png" alt="Ejemplo venta" class="manual-img">
                <p class="justificado">
                    <strong>Moneda adicional:</strong> Existe un apartado que es desplegable donde se puede seleccionar
                    otra moneda
                </p>
                <img src="img/moneda.png" alt="Ejemplo venta" class="manual-img">
                <h3 id="visualizacion_ventas" class="contenido ms-3">2.2 Visualización/edición de datos</h3>
                <p class="justificado">
                    <strong> Visualización de las ventas del día:</strong> Se podrán ver todas las
                    ventas del día con todos los datos que le correspondan

                </p>
                <img src="img/visualizacion.png" alt="Ejemplo venta" class="manual-img">
                <p class="justificado">
                    <strong>Edición de ventas registradas:</strong> Se pueden
                    editar los registros que se muestran en la tabla, si así se requiere solo dándole a editar
                    Se mostrarán los datos en los campos listos para su edición
                </p>
                <p class="justificado">
                    <strong>Editar moneda:</strong> Si se requiere cambiar de moneda se
                    puede hacer desde aquí, solo actualizando la moneda requerida
                    Se puede cancelar toda la edición con un nuevo botón que se genera al lao de la página
                </p>
                <img src="img/edicion.png" alt="Ejemplo venta" class="manual-img">
                <h3 id="reglas_ventas" class="contenido ms-3">2.3 Reglas de registros de ventas</h3>
                <h4 id="no_dobles" class="contenido ms-4">No se permite registros dobles</h4>
                <p class="justificado">
                    Si se requiere cambiar de moneda se
                    puede hacer desde aquí, solo actualizando la moneda requerida
                    Se puede cancelar toda la edición con un nuevo botón que se genera al lao de la página
                </p>
                <img src="img/registrodoble.png" alt="Ejemplo venta" class="manual-img">
                <h4 id="especiales" class="contenido ms-4">Ingresar registros dobles especiales</h4>
                <p class="justificado">
                    Hay casos donde la misma persona compra el curso dos veces en distintos tiempo, entonces para
                    ingresar esa venta especial se tiene que agregar el sufijo _ESP antes del canal |canal, y escribir
                    una observación aclarando porque es especial
                </p>
                <p class="justificado">
                    Existe el asistente que puede añadir el sufijo por ti y tambíen abrir el campo de observaciones
                </p>
                <img src="img/dobleespecial.png" alt="Ejemplo venta" class="manual-img">
                <h4 id="importante_precio" class="contenido ms-4">Importante sobre esta sección</h4>
                <p>
                    <em>
                        <strong>
                            No se permitirá el ingreso del registro hasta que el sufijo y la observación
                            agregada, los botones permanecerán bloqueados
                        </strong>
                    </em>
                </p>
                <h4 id="anomalo" class="contenido ms-4">Sobre precios anómalos</h4>
                <p class="justificado">
                    Los registros con precios anómalos serán también observados, para ello se tienen diferentes alertas:
                </p>
                <ul>
                    <p>
                        Si un producto está sugerido como soles pero por error humano se ingresa como pesos saldrá
                        la alerta, donde se puede proceder con la inserción o simplemente cancelar y corregir
                    </p>
                    <img src="img/anomalo1.png" alt="Ejemplo venta" class="manual-img">
                    <p>
                        En el caso de pesos a soles es la misma lógica
                    </p>
                    <img src="img/anomalo2.png" alt="Ejemplo venta" class="manual-img">
                </ul>
                <h3 id="comisiones" class="contenido ms-3">2.4 Sub módulo de comisiones por ventas</h3>
                <h4 id="acceso_comision" class="contenido ms-4">Acceso</h4>
                <p class="justificado">
                    Desde la página de registrar ventas sobre el formulario se encuentra el acceso a este sub-módulo
                </p>
                <h4 id="agregar_comision" class="contenido ms-4">Agregar comisión</h4>
                <p class="justificado">
                    Para agregar comisión según las políticas de la organización, se necesita lo siguiente:
                </p>
                <ul>
                    <p>
                        - Venta de un curso que no esté en oferta
                    </p>
                    <p>
                        - Tener captura del chat y del comprobante subido a la carpeta drive asignada
                    </p>
                </ul>
                <h4 id="subir_comprobante" class="contenido ms-4">Subir imagen/captura de comprobante a drive
                </h4>
                <p class="justificado">
                    En la parte superior izquierda se encuentra el enlace/botón a la carpeta designada para subir los
                    comprobantes
                </p>
                <p class="justificado">
                    Luego de subir la imágen a dicha carpeta solo se tiene que copiar el link de compartir
                </p>
                <figure>
                    <img src="img/comision1.png" class="manual-img" alt="Vista del registro de ventas">
                    <figcaption class="text-center text-muted mt-2">Una vez con los enlaces en el portapapeles se puede
                        seguir con el registro
                    </figcaption>
                </figure>
                <h4 id="formulario_comision" class="contenido ms-4">Llenar campos de formulario para registrar
                    una comisión
                </h4>
                <p class="justificado">
                    Los campos se llenan de la siguiente forma
                </p>
                <p>
                    <strong>
                        Información de comprobante
                    </strong>
                </p>
                <ul>
                    <p>
                        Número de operación-número que aparece en el comprobante enviado por el cliente
                    </p>
                    <p>
                        Correo del cliente
                    </p>
                    <p>
                        Fecha del comprobante- fecha que aparece en el comprobante
                    </p>
                    <p>
                        Comprobantes-Copiar links de drive de los comprobantes
                    </p>
                    <ul>
                        <img src="img/comision2.png" alt="Ejemplo venta" class="manual-img">
                    </ul>
                </ul>
                <p>
                    <strong>
                        Información de producto
                    </strong>
                </p>
                <ul>
                    <p>
                        Producto-nombre del producto
                    </p>
                    <p>
                        Precio-precio de la venta del producto
                    </p>
                    <p>
                        Canal-canal por el cual se hizo la venta
                    </p>
                </ul>
                <ul>
                    <img src="img/comision3.png" alt="Ejemplo venta" class="manual-img">

                </ul>

                <p>
                    <strong>
                        Otros:
                    </strong>
                </p>
                <ul>
                    <p>
                        Comisión compartida-si la comisión es compartida se marca este check
                    </p>
                    <h4 id="extra_comision" class="contenido ms-4">Extra</h4>
                    <p>
                        <em>Si no sabe con quien comparte la comisión puede poner canal canal de donde proviene la
                            venta, un administrador se encargará de rastrear al vendedor con el que se compartirá la
                            comisión
                        </em>
                    </p>
                    <p>
                        Descripción (opcional)
                    </p>
                    <ul>
                        <img src="img/comision4.png" alt="Ejemplo venta" class="manual-img">
                    </ul>
                </ul>
                <h4 id="ver_comisiones" class="contenido ms-4">Visualización de registros</h4>
                <p>Se tiene una tabla donde se puede ver todas los registros de comisiones hasta la fecha se pueden
                    editar y ver los comprobantes
                </p>
                <img src="img/comision5.png" alt="Ejemplo venta" class="manual-img">
                <p> Se puede visualizar también si la comisión ya ha sido procesada

                </p>
                <img src="img/comision6.png" alt="Ejemplo venta" class="manual-img">
                <h2 id="reportes" class="contenido">3. Módulo de Reportes</h2>

                <h3 id="que_es_reporte" class="contenido ms-3">3.1 Qué es un reporte</h3>
                <p>
                    Los reportes se registran por día. Se mencionan aspectos importantes como problemas, cursos más
                    vendidos, dudas,
                    clientes potenciales y recomendaciones diarias.
                </p>

                <h3 id="ingresar_reporte" class="contenido ms-3">3.2 Ingresar un reporte</h3>

                <h4 id="acceso_reporte" class="contenido ms-4">Acceso</h4>
                <p>
                    En el menú principal superior.
                </p>
                <img src="img/accsesoventas.png" alt="Menú con opción de reportes" class="manual-img">

                <h4 id="agregar_reporte" class="contenido ms-4">Agregar un reporte</h4>
                <p>
                    En la parte superior derecha se encuentra el botón para agregar un nuevo reporte.
                </p>
                <img src="img/gestion_reportes.png" alt="Gestión de reportes" class="manual-img">

                <p><strong>Los campos a llenar son:</strong></p>

                <h5 id="tipo_reporte" class="contenido ms-4">Tipo de reporte</h5>
                <p>
                    Se escoge qué tipo de reporte es. Por el momento, solo se utiliza el tipo "diario".
                </p>
                <img src="img/tipo_reporte.png" alt="Tipo de reporte" class="manual-img">

                <h5 id="problemas" class="contenido ms-4">Problemas</h5>
                <p>
                    Se ingresan los problemas que se tuvieron en el día. Se pueden agregar cuantos campos sean
                    necesarios.
                </p>
                <img src="img/problemas.png" alt="Formulario para problemas" class="manual-img">

                <h5 id="cursos_vendidos" class="contenido ms-4">Cursos más vendidos</h5>
                <p>
                    Se reportan los cursos más vendidos del día, con la misma lógica que problemas: puedes crear cuantos
                    campos requieras.
                </p>
                <img src="img/cursos_vendidos.png" alt="Campos para cursos más vendidos" class="manual-img">

                <h5 id="dudas" class="contenido ms-4">Dudas frecuentes</h5>
                <p>
                    Se reportan las dudas frecuentes que se tuvieron durante el día con la misma lógica.
                </p>
                <img src="img/dudas_frecuentes.png" alt="Ejemplo de duda frecuente" class="manual-img">

                <h5 id="clientes_potenciales" class="contenido ms-4">Clientes potenciales</h5>
                <p>
                    En esta sección se añaden los clientes potenciales que pueden convertirse en ventas. Existen varios
                    campos para estos:
                </p>
                <ul>
                    <li>Nombre</li>
                    <li>Teléfono (si tiene)</li>
                    <li>Email (si tiene)</li>
                    <li>Descripción (detalle sobre la posible venta)</li>
                    <li>Estado (Nuevo, Interesado, Negociación, Comprometido, Vendido, Perdido)</li>
                    <li>Canal (opciones para elegir o poner uno personalizado)</li>
                </ul>
                <img src="img/canales_clientes.png" alt="Selección de canal para cliente potencial" class="manual-img">

                <p>
                    También se puede añadir un <strong>recordatorio</strong> con fecha para mejor seguimiento. Se
                    mostrará una notificación en el inicio cuando se acerque la fecha.
                </p>
                <img src="img/recordatorio_cliente.png" alt="Campo para recordatorio en cliente potencial"
                    class="manual-img">

                <h4 id="ver_reportes" class="contenido ms-4">Visualización de datos/reportes</h4>
                <p>
                    Se pueden ver y editar todos los reportes registrados. También se pueden ordenar, buscar, o eliminar
                    si es necesario.
                </p>
                <img src="img/tabla_reportes.png" alt="Tabla de reportes registrados" class="manual-img">


                <h2 id="seguimientos" class="contenido">4. Módulo de Seguimientos</h2>

                <h3 id="que_es_seguimiento" class="contenido ms-3">4.1 Para lo que es un seguimiento</h3>
                <p class="justificado">
                    Los clientes potenciales de los reportes van a parar a esta sección, están divididos por dos grandes
                    grupos “Clientes en Proceso” y “Clientes Finalizados”, el objetivo es que todos los clientes en
                    proceso o la mayoría vayan pasando a clientes finalizados. Es cliente finalizado cuando sus dos
                    únicos estados pueden ser <em>perdido</em> o <em>vendido</em>.
                </p>

                <h3 id="manejo_seguimiento" class="contenido ms-3">4.2 Manejo de seguimientos</h3>

                <h4 id="acceso_seguimiento" class="contenido ms-4">Acceso</h4>
                <p class="justificado">Se encuentra en el menú principal superior</p>
                <img src="img/accsesoventas.png" alt="Menú de acceso a seguimientos" class="manual-img">

                <h4 id="seguimiento_cliente" class="contenido ms-4">Hacer seguimiento a un cliente</h4>
                <p class="justificado">
                    Se le ubica con los datos registrados, y se procede con la continuación de la venta. Dependiendo de
                    la interacción se edita el estado, descripción o la fecha de recuerdo.
                </p>
                <img src="img/seguimiento_estado.png" alt="Estados de seguimiento" class="manual-img">

                <h4 id="recordatorios" class="contenido ms-4">Hacer seguimiento a mis clientes con recordatorio</h4>
                <p class="justificado">Existen 3 filtros en la parte superior:</p>
                <ul>
                    <li><strong>Mostrar recordatorios Actuales/futuros:</strong> Mostrará tus recordatorios con fecha de
                        hoy o con fecha futura.</li>
                    <li><strong>Mostrar recordatorios:</strong> Mostrará solo tus recordatorios pasados.</li>
                    <li><strong>Ver todos:</strong> Volverá todo a la normalidad y mostrará todos los recordatorios.
                    </li>
                </ul>
                <img src="img/filtros_recordatorios.png" alt="Filtros de recordatorio" class="manual-img">

                <h3 id="notificaciones" class="contenido ms-3">4.3 Seguimientos desde notificaciones en página principal
                </h3>

                <h4 id="hoy" class="contenido ms-4">Recordatorios para hoy</h4>
                <p class="justificado">Solo muestra los recordatorios para hoy.</p>

                <h4 id="vencidos" class="contenido ms-4">Recordatorios Vencidos</h4>
                <p class="justificado">Solo muestra los recordatorios vencidos.</p>

                <img src="img/recordatorios_alertas.png" alt="Alertas de recordatorios" class="manual-img">

                <h4 id="ver_recordatorios" class="contenido ms-4">Ver recordatorios</h4>
                <p class="justificado">Puedes hacer clic en el botón <em>Ver</em> para mostrar los recordatorios
                    pendientes de tus clientes.</p>
                <img src="img/recordatorios_botonver.png" alt="Botón Ver recordatorio" class="manual-img">


                <h2 id="puntos" class="contenido">5. Sistema de puntos y recompensas</h2>
                <h3 id="semanales" class="contenido ms-3">5.1 Recompensas semanales</h3>
                <h4 id="ganancia_semanal" class="contenido ms-4">Ganancia de puntos</h4>
                <h4 id="visualizar_catalogo" class="contenido ms-4">Visualización de puntos y catálogo de
                    recompensas
                </h4>
                <h3 id="mensuales" class="contenido ms-3">5.2 Recompensas mensuales (grupal)</h3>
                <h4 id="ganancia_mensual" class="contenido ms-4">Ganancia de puntos</h4>
                <h4 id="progreso_mensual" class="contenido ms-4">Visualización de puntos y progreso</h4>
                <h3 id="historicos" class="contenido ms-3">5.3 Recompensas históricas</h3>
                <h4 id="ganancia_historica" class="contenido ms-4">Ganancia de puntos</h4>
                <h4 id="solicitar_actividad" class="contenido ms-4">Pasos para Solicitar puntos por Actividad
                    específica
                </h4>
                <h3 id="ver_recompensas" class="contenido ms-3">5.4 Visualización de datos de recompensas/puntos
                </h3>
                <h4 id="ver_semanal_mensual" class="contenido ms-4">Recompensas semanales y mensuales</h4>
                <h4 id="ver_historicos" class="contenido ms-4">Recompensas históricas</h4>
                <h3 id="sanciones" class="contenido ms-3">EXTRA: Sanciones de puntos y bonus administrados por
                    admin
                </h3>

                <h2 id="asistencias" class="contenido">6. Módulo de Asistencias</h2>
                <h4 id="acceso_asistencia" class="contenido ms-4">Acceso</h4>
                <h4 id="ver_asistencias" class="contenido ms-4">Visualización de datos</h4>
                <h4 id="minutos" class="contenido ms-4">Minutos pendientes por recuperar</h4>
                <h4 id="historial_asistencia" class="contenido ms-4">Historial de asistencia</h4>
            </main>


        </div>
    </div>

    <!-- Buscador -->
    <script>
        const buscador = document.getElementById('buscador');
        const items = document.querySelectorAll('#indice .nav-link');

        buscador.addEventListener('input', () => {
            const val = buscador.value.toLowerCase();
            items.forEach(link => {
                const visible = link.textContent.toLowerCase().includes(val);
                link.style.display = visible ? '' : 'none';
            });
        });
    </script>
</body>

</html>