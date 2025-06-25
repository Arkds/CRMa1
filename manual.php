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
        main {
    padding-top: 1rem;
}


#menu {
    position: sticky;
    top: 0;
    height: 100vh;
    overflow-y: auto;
    border-right: 1px solid #ccc;
    z-index: 1020;
    background-color: #f8f9fa;
}
#menu::-webkit-scrollbar {
    width: 6px;
}
#menu::-webkit-scrollbar-thumb {
    background-color: #bbb;
    border-radius: 3px;
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
       #buscador-container {
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 1030;
    padding: 12px 16px 10px;
    border-bottom: 1px solid #ccc;
}
#indice {
    margin-top: 0 !important;
}

#buscador {
    font-size: 0.95rem;
}
#btnSubir {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 1050;
    background-color: #0d6efd;
    color: white;
    border: none;
    border-radius: 50%;
    width: 45px;
    height: 45px;
    font-size: 20px;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.3);
    display: none;
    cursor: pointer;
    transition: opacity 0.3s ease;
}
#btnSubir:hover {
    background-color: #0b5ed7;
}


    </style>
</head>

<body>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Índice lateral -->
            <nav id="menu" class="col-md-3 col-sm-4 bg-light p-0">
    <div id="buscador-container" class="p-3">
        <input type="text" id="buscador" class="form-control" placeholder="Buscar sección...">
    </div>
<ul id="indice" class="nav flex-column mt-3 pt-2">

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
<p class="justificado">
    Se tiene un sistema de puntos donde se premia a la consistencia en el desarrollo de actividades del trabajo, se trata de tres tipos de recompensas: <strong>Semanales</strong>, <strong>Mensuales (grupal)</strong> e <strong>histórico</strong>.
</p>

<h3 id="semanales" class="contenido ms-3">5.1 Recompensas semanales</h3>
<p class="justificado">
    Los puntos ganados se reinician cada lunes a las 00:00 y para ganar puntos solo se toma en cuenta las ventas de lunes a domingo.
</p>

<ul>
    <li>
        <strong>Ganancia de puntos</strong><br>
        <span class="justificado">
            La ganancia de puntos se basan en ventas por comisiones y ventas normales, se ganan <strong>100 puntos</strong> por comisión registrada, se ganan <strong>15 puntos</strong> por cada venta normal.
        </span>
    </li>
    <li class="mt-2">
        <strong>Visualización de puntos y catálogo de recompensas</strong><br>
        <span class="justificado">
            Se pueden ver los puntos y las recompensas disponibles en la sección de <em>Mi Progreso</em>, también se pueden reclamar las recompensas alcanzadas, pero solo se pueden reclamar una vez.
        </span>
    </li>
</ul>
 <img src="img/puntos1.png" alt="a" class="manual-img">

                <<h3 id="mensuales" class="contenido ms-3">5.2 Recompensas mensuales (grupal)</h3>
<p class="justificado">
    Se armaron grupos por horarios mañana, tarde y noche, los puntos se reinician cada 1 de cada mes a las 00:00, y para ganar puntos solo se toma en cuenta de lunes a viernes en el horario de su grupo asignado.
</p>

<ul>
    <li>
        <strong>Ganancia de puntos</strong><br>
        <span class="justificado">
            Se asignan puntos cada vez que se hace una venta de <strong>150 MXN</strong> o mayor y <strong>29.90 PEN</strong> o mayor. Cada condición suma <strong>180 puntos</strong> y se suma entre los miembros del grupo.
        </span>
    </li>
    <li class="mt-2">
        <strong>Visualización de puntos y progreso</strong><br>
        <span class="justificado">
            Para ello se muestra la recompensa más cercana, una barra de progreso con marcas de los puntos necesaria a conseguir.
        </span>
    </li>
</ul>

<img src="img/recompensas_mensuales_equipo.png" alt="Progreso de recompensas mensuales del equipo" class="manual-img">

                <h3 id="historicos" class="contenido ms-3">5.3 Recompensas históricas</h3>
<p class="justificado">
    Estas recompensas son para siempre desde la fecha que decida poner el administrador, no se reinician hasta que el admin lo decida, para ganar puntos se toman en cuenta todos los días y todos los horarios.
    <strong>Se accede desde el menú principal en el icono de estadísticas</strong>.
</p>
<img src="img/acceso_estadisticas.png" alt="Acceso a estadísticas" class="manual-img">

<h4 id="ganancia_historica" class="contenido ms-4">Ganancia de puntos</h4>
<p class="justificado">
    Se asignan puntos cada vez que se hace una venta de cualquier tipo. Se suman <strong>35 puntos</strong> por cada venta.
    También se pueden solicitar puntos por actividades específicas que son:
</p>
<ul>
    <li><strong>Venta difícil:</strong> Cuando una venta costó muchos recurso humano de convencimiento o de por sí existieron dificultades pero se logró hacer la venta.</li>
    <li><strong>Seguimiento con tres ventas:</strong> Se hacen un bloque de seguimiento de la sección de seguimientos y se hacen 3 ventas.</li>
    <li><strong>Ventas cruzadas:</strong> Se venden más de uno o más productos relacionados a un producto ya vendido o negociado.</li>
</ul>

<h4 id="solicitar_actividad" class="contenido ms-4">Pasos para Solicitar puntos por Actividad específica</h4>

<ul>
    <li>
        <strong>Ubicación de sección:</strong> Se encuentra en la parte inferior de la página de puntos históricos.
    </li>
</ul>
<img src="img/solicitud_puntos_formulario.png" alt="Formulario de solicitud de puntos" class="manual-img">

<ul>
    <li>
        <strong>Tipo de actividad:</strong> Se selecciona la actividad a solicitar en el campo <em>Tipo de Actividad</em>.
    </li>
</ul>
<img src="img/tipo_actividad_lista.png" alt="Opciones del tipo de actividad" class="manual-img">

<ul>
    <li>
        <strong>Enlace a la evidencia:</strong> Se pega el enlace a la evidencia al igual que en comisiones, la ubicación para subir estas imágenes es en la carpeta <strong>00.PUNTOS</strong>.
    </li>
</ul>
<img src="img/carpeta_evidencia.png" alt="Ubicación de carpeta 00.PUNTOS" class="manual-img">

<ul>
    <li>
        <strong>Comentarios adicionales:</strong> Se justifica brevemente sobre los puntos que se está solicitando.
    </li>
    <li>
        <strong>Procesamiento de solicitudes:</strong> Un administrador verificará tu solicitud y se mostrará en una tabla el estado de estas.
    </li>
</ul>
<img src="img/tabla_solicitudes.png" alt="Tabla de solicitudes de puntos por actividad" class="manual-img">

                <h3 id="ver_recompensas" class="contenido ms-3">5.4 Visualización de datos de recompensas/puntos</h3>

<h4 id="ver_semanal_mensual" class="contenido ms-4">Recompensas semanales y mensuales</h4>
<p class="justificado">
    <strong>Ubicación:</strong> en la página principal, en la sección de catálogos y progresos.
</p>
<img src="img/recompensas_disponibles.png" alt="Catálogo de recompensas semanales y mensuales" class="manual-img">
<img src="img/mi_progreso.png" alt="Gráfico de progreso semanal/mensual y reclamación" class="manual-img">
<img src="img/recompensas_mensuales_equipo.png" alt="Recompensas mensuales del equipo" class="manual-img">

<h4 id="ver_historicos" class="contenido ms-4">Recompensas históricas</h4>
<p class="justificado">
    Ya se mencionó el acceso, que es desde el menú principal en el icono de barras.
</p>
<img src="img/acceso_estadisticas.png" alt="Acceso a estadísticas del historial" class="manual-img">

<p class="justificado">
    En esa página, primero se muestra una barra con el progreso al lado del catálogo.
</p>
<img src="img/progreso_historico.png" alt="Progreso de puntos históricos y catálogo" class="manual-img">

<p class="justificado">
    Cuando se reclama alguna recompensa se podrá hacer desde el catálogo y se mostrará su estado <strong>pendiente</strong> o <strong>pagado</strong>.
</p>
<img src="img/recompensas_alcanzadas.png" alt="Recompensas alcanzadas y próximas" class="manual-img">

<p class="justificado">
    También se muestra una tabla con el historial de puntos asignados.
</p>
<img src="img/historial_puntos.png" alt="Historial de puntos asignados" class="manual-img">


<h3 id="sanciones" class="contenido ms-3">EXTRA: Sanciones de puntos y bonus administrados por admin</h3>
<p class="justificado">
    Se puede sancionar con quitar puntos por un administrador en los siguientes casos:
</p>
<ul>
    <li><strong>Semana sin errores:</strong> Ingreso de ventas y reportes sin problemas ni errores.</li>
    <li><strong>Error en registro:</strong> Un error en un registro dentro del CRM.</li>
    <li><strong>Faltar sin aviso:</strong> Faltar un día sin comunicarlo.</li>
    <li><strong>Engaño/inventar venta:</strong> Si se descubre esta actividad, además de restar <strong>1000 puntos</strong>, se puede suspender temporalmente al usuario del sistema de puntos.</li>
</ul>
<img src="img/tipo_puntos_sanciones.png" alt="Tipos de sanción o bonificación" class="manual-img">


                <h2 id="asistencias" class="contenido">6. Módulo de Asistencias</h2>
<p class="justificado">
    Un espacio para marcar asistencia, para gestionar minutos de recuperación.
</p>

<h4 id="acceso_asistencia" class="contenido ms-4">Acceso</h4>
<p class="justificado">
    En la página de inicio se tiene una sección donde se puede marcar la entrada y salida del turno, desde esa sección se puede acceder al historial de registros de asistencia.
</p>

<h4 id="ver_asistencias" class="contenido ms-4">Visualización de datos</h4>
<p class="justificado">
    Se pueden visualizar los datos en las siguientes secciones.
</p>

<h4 id="minutos" class="contenido ms-4">Minutos pendientes por recuperar</h4>
<p class="justificado">
    Se muestran arriba del todo, se va acumulando de acuerdo a lo que acumules.
</p>
<img src="img/minutos_pendientes.png" alt="Minutos pendientes por recuperar" class="manual-img">

<h4 id="historial_asistencia" class="contenido ms-4">Historial de asistencia</h4>
<p class="justificado">
    Muestra el historial completo de asistencias.
</p>
<img src="img/historial_asistencia.png" alt="Tabla de historial de asistencias" class="manual-img">

<p class="justificado">
    También se muestra el historial de recuperaciones de hora:
</p>
<img src="img/recuperaciones_horas.png" alt="Historial de recuperaciones de horas" class="manual-img">

            </main>


        </div>
    </div>
    <button id="btnSubir" title="Volver arriba">↑</button>


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
    <script>
    const btnSubir = document.getElementById("btnSubir");

    window.onscroll = function () {
        btnSubir.style.display = (document.documentElement.scrollTop > 300) ? "block" : "none";
    };

    btnSubir.addEventListener("click", () => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
</script>


</body>

</html>