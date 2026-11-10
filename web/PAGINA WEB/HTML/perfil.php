<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = "localhost";
$user = "root";           
$pass = "";               
$db   = "marvel"; 

$conexion_perfil = new mysqli($host, $user, $pass, $db);
if ($conexion_perfil->connect_error) {
    die("Error de conexión al Helicarrier.");
}
$conexion_perfil->set_charset("utf8");

if (!isset($_SESSION['id_usuario'])) {
    header("Location: ../../index.php");
    exit;
}

$id_usuario = intval($_SESSION['id_usuario']);
$mensaje_consola = "";

// ========================================================
// PROCESADOR DE VENTAS Stark Tech: SOLO PARA REPETIDAS
// ========================================================
if (isset($_POST['vender_carta_id'])) {
    $id_carta_vender = intval($_POST['vender_carta_id']);
    
    // 1. Contamos cuántas copias tiene el usuario de esta carta exacta
    $count_query = $conexion_perfil->query("SELECT COUNT(*) AS total FROM usuario_cartas WHERE id_usuario = $id_usuario AND id_carta = $id_carta_vender");
    $count_data = $count_query->fetch_assoc();
    $cantidad_actual = intval($count_data['total']);

    // Solo se permite la venta si tiene 2 o más (es decir, si tiene repetidas)
    if ($cantidad_actual > 1) {
        // 2. Buscamos la rareza para determinar el precio justo de reembolso
        $item_query = $conexion_perfil->query("SELECT rareza, nombre_personaje FROM cartas WHERE id_carta = $id_carta_vender");
        $item_data = $item_query->fetch_assoc();
        $rareza = $item_data['rareza'];
        $nombre_c = $item_data['nombre_personaje'];

        $reembolso = 25; // Común por defecto
        if ($rareza === 'Epica') $reembolso = 100;
        if ($rareza === 'Legendaria') $reembolso = 300;

        // 3. Transacción atómica segura: eliminamos SOLO UNA fila de la tabla intermedia y sumamos las monedas
        $conexion_perfil->begin_transaction();
        
        // El LIMIT 1 asegura que solo se borre una de las copias repetidas
        $borrar = $conexion_perfil->query("DELETE FROM usuario_cartas WHERE id_usuario = $id_usuario AND id_carta = $id_carta_vender LIMIT 1");
        $sumar_monedas = $conexion_perfil->query("UPDATE usuarios SET monedas_marvel = monedas_marvel + $reembolso WHERE id_usuario = $id_usuario");

        if ($borrar && $sumar_monedas) {
            $conexion_perfil->commit();
            $mensaje_consola = "Transaccion aprobada. Has vendido una copia repetida de " . $nombre_c . " por " . $reembolso . " Monedas.";
        } else {
            $conexion_perfil->rollback();
            $mensaje_consola = "Fallo de conexion en los servidores de la tienda al procesar el reembolso.";
        }
    } else {
        $mensaje_consola = "Accion denegada. Solo puedes vender copias repetidas de tus personajes.";
    }
}

// CONFIGURACIONES PREVIAS DE AVATARS Y COSMÉTICOS
if (isset($_FILES['avatar_subido']) && $_FILES['avatar_subido']['error'] == 0) {
    $carpeta_destino = "../../IM_USUARIOS/";
    if (!file_exists($carpeta_destino)) mkdir($carpeta_destino, 0777, true);
    $extension = strtolower(pathinfo($_FILES['avatar_subido']['name'], PATHINFO_EXTENSION));
    $formatos_permitidos = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($extension, $formatos_permitidos)) {
        $nombre_final = "user_" . $id_usuario . "." . $extension;
        $ruta_completa = $carpeta_destino . $nombre_final;
        if (move_uploaded_file($_FILES['avatar_subido']['tmp_name'], $ruta_completa)) {
            $conexion_perfil->query("UPDATE usuarios SET avatar_url = '$ruta_completa' WHERE id_usuario = $id_usuario");
            $_SESSION['avatar_actual'] = $ruta_completa;
            $mensaje_consola = "Enlace biométrico establecido de forma permanente.";
        }
    }
}

if (isset($_POST['avatar_url_predeterminado']) && !empty($_POST['avatar_url_predeterminado'])) {
    $url_p = $conexion_perfil->real_escape_string($_POST['avatar_url_predeterminado']);
    $conexion_perfil->query("UPDATE usuarios SET avatar_url = '$url_p' WHERE id_usuario = $id_usuario");
    $_SESSION['avatar_actual'] = $url_p;
    $mensaje_consola = "Avatar de perfil sincronizado de forma permanente.";
}

if (isset($_GET['equipar'])) {
    $id_item_equipar = intval($_GET['equipar']);
    $verificar = $conexion_perfil->query("SELECT categoria, nombre FROM tienda_cosmeticos WHERE id_item = $id_item_equipar");
    if ($verificar && $verificar->num_rows > 0) {
        $item = $verificar->fetch_assoc();
        $categoria = $item['categoria'];
        $conexion_perfil->query("UPDATE inventario_cosmeticos ic INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item SET ic.equipado = 0 WHERE ic.id_usuario = $id_usuario AND t.categoria = '$categoria'");
        $conexion_perfil->query("UPDATE inventario_cosmeticos SET equipado = 1 WHERE id_usuario = $id_usuario AND id_item = $id_item_equipar");
    }
}

$datos_usuario = $conexion_perfil->query("SELECT username, monedas_marvel, avatar_url FROM usuarios WHERE id_usuario = $id_usuario")->fetch_assoc();
$avatar_final = !empty($datos_usuario['avatar_url']) ? $datos_usuario['avatar_url'] : "../../IM_BUSQUEDA/LOGITO.jpg";

$marco_equipado = ""; $titulo_equipado = "RECLUTA DE S.H.I.E.L.D."; $fondo_equipado_url = ""; 
$consulta_equipados = $conexion_perfil->query("SELECT t.id_item, t.nombre, t.categoria FROM inventario_cosmeticos ic INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item WHERE ic.id_usuario = $id_usuario AND ic.equipado = 1");
if ($consulta_equipados) {
    while ($eq = $consulta_equipados->fetch_assoc()) {
        if ($eq['categoria'] === 'Marco') {
            $marco_equipado = (intval($eq['id_item']) === 1) ? "../../IM_COSMETICOS/Marco_Ojo_Halcon_Cosmetico.png" : "../../IM_COSMETICOS/item_" . $eq['id_item'] . ".png";
        } elseif ($eq['categoria'] === 'Titulo') {
            $titulo_equipado = $eq['nombre'];
        } elseif ($eq['categoria'] === 'Fondo') {
            $fondo_equipado_url = "../../IM_COSMETICOS/item_" . $eq['id_item'] . ".png";
        }
    }
}

$inventario = $conexion_perfil->query("SELECT t.id_item, t.nombre, t.categoria, ic.equipado FROM inventario_cosmeticos ic INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item WHERE ic.id_usuario = $id_usuario");

// CAMBIO CLAVE EN LA CONSULTA: Agrupamos con COUNT(uc.id_carta) para calcular las copias exactas que posee de cada personaje
$mis_cartas = $conexion_perfil->query("
    SELECT c.id_carta, c.nombre_personaje, c.rareza, c.ataque, c.defensa, c.ruta_imagen_carta, c.porcentaje_drop, c.descripcion, COUNT(uc.id_carta) AS cantidad_poseida
    FROM usuario_cartas uc 
    INNER JOIN cartas c ON uc.id_carta = c.id_carta 
    WHERE uc.id_usuario = $id_usuario
    GROUP BY c.id_carta
");
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Perfil - @<?php echo htmlspecialchars($datos_usuario['username']); ?></title>
  <link rel="stylesheet" href="../../CSS/style.css">
  <link rel="stylesheet" href="../../CSS/perfil.css">
</head>
<body class="perfil-body">

  <header class="topbar-perfil">
    <div class="topbar-left">
      <a href="../../../index.html">← Regresar</a>
    </div>
    <div class="topbar-right">
      <a href="tienda.html">Tienda Stark</a>
    </div>
  </header>

  <!-- CABECERA EXPANDIDA CONFIGURADA CON FONDO DINÁMICO ADQUIRIDO -->
  <div class="perfil-cabecera">
    <div class="perfil-banner" style="<?php echo !empty($fondo_equipado_url) ? "background-image: url('$fondo_equipado_url') !important;" : ""; ?>"></div>

    <div class="perfil-info-bloque">
      <div class="avatar-posicionador">
        <img src="<?php echo $avatar_final; ?>" alt="Avatar" class="avatar-imagen-core">
        <?php if (!empty($marco_equipado)): ?>
            <img src="<?php echo $marco_equipado; ?>" alt="Marco" class="avatar-marco-render">
        <?php endif; ?>
      </div>

      <h2 class="user-tag-name">@<?php echo htmlspecialchars($datos_usuario['username']); ?></h2>
      <p class="user-sub-status">TÍTULO: <?php echo htmlspecialchars($titulo_equipado); ?></p>
      
      <div class="user-wallet">
        Fondos disponibles: <strong><?php echo $datos_usuario['monedas_marvel']; ?> Monedas</strong>
      </div>
    </div>
  </div>


  <main class="perfil-contenido-cuerpo">
    <?php if (!empty($mensaje_consola)): ?>
        <div class="notif-barra"><?php echo $mensaje_consola; ?></div>
    <?php endif; ?>

    <div class="pestanas-menu">
      <button class="pestana-link activa" onclick="abrirPestana(event, 'tab-cosmeticos')">Cosméticos</button>
      <button class="pestana-link" onclick="abrirPestana(event, 'tab-tarjetas')">Tarjetas</button>
    </div>

    <!-- PESTAÑA 1: COSMÉTICOS -->
    <div id="tab-cosmeticos" class="seccion-tab activa">
      <div class="grid-items-inventario">
        <?php if ($inventario && $inventario->num_rows > 0): ?>
            <?php while($item = $inventario->fetch_assoc()): ?>
                <div class="card-item-propio <?php echo $item['equipado'] ? 'equipado-si' : ''; ?>">
                    <div class="card-item-tipo"><?php echo htmlspecialchars($item['categoria']); ?></div>
                    <h4 class="card-item-title"><?php echo htmlspecialchars($item['nombre']); ?></h4>
                    
                    <?php if ($item['equipado']): ?>
                        <button class="btn-perfil-action btn-equipado">Equipado</button>
                    <?php else: ?>
                        <a href="perfil.php?equipar=<?php echo $item['id_item']; ?>">
                            <button class="btn-perfil-action">Equipar</button>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #64748b; font-style: italic; text-align: center; width: 100%;">No posees personalizaciones en tu inventario actual.</p>
        <?php endif; ?>
      </div>

      <div class="panel-config-foto">
        <h4>Sincronización de Enlace Biométrico</h4>
        <div class="split-foto-controles">
          <form action="perfil.php" method="POST" enctype="multipart/form-data" class="form-foto-bloque">
            <label class="config-label">Subir archivo local (PNG, JPG):</label>
            <input type="file" name="avatar_subido" class="input-file-stark" required>
            <button type="submit" class="btn-perfil-action">Cargar Imagen</button>
          </form>

          <form action="perfil.php" method="POST" class="form-foto-bloque">
            <label class="config-label">Elegir Héroe de S.H.I.E.L.D.:</label>
            <select name="avatar_url_predeterminado" class="selector-rapido-avatar" required>
              <option value="">-- Seleccionar --</option>
              <option value="../../IM_BUSQUEDA/LOGITO.jpg">Logotipo de Marvel</option>
              <option value="../../IM_CREADORES/Stan_Lee.webp">Stan Lee</option>
              <option value="../../IM_BUSQUEDA/guardianes.png">Guardianes de la Galaxia</option>
            </select>
            <button type="submit" class="btn-perfil-action">Establecer Héroe</button>
          </form>
        </div>
      </div>
    </div>

    <!-- PESTAÑA 2: TARJETAS COLECTADAS -->
    <div id="tab-tarjetas" class="seccion-tab">
      <div class="grid-items-inventario">
        <?php if ($mis_cartas && $mis_cartas->num_rows > 0): ?>
            <?php while($card = $mis_cartas->fetch_assoc()): ?>
                <?php 
                $ruta_img_js = "../../IM_CARTAS/" . urlencode(basename($card['ruta_imagen_carta']));
                $desc_js = addslashes($card['descripcion'] ?? 'Sin descripcion disponible.');
                $nombre_js = addslashes($card['nombre_personaje']);
                $rareza_js = addslashes($card['rareza']);
                $cantidad = intval($card['cantidad_poseida']);
                $id_carta = intval($card['id_carta']);
                ?>
                <div class="card-item-propio clickable" style="border-left: 4px solid <?php echo ($card['rareza'] === 'Comun') ? '#475569' : (($card['rareza'] === 'Epica') ? '#7c3aed' : '#ca8a04'); ?>;" 
                     onclick="abrirDetalleCarta('<?php echo $nombre_js; ?>', '<?php echo $ruta_img_js; ?>', '<?php echo intval($card['ataque']); ?>', '<?php echo intval($card['defensa']); ?>', '<?php echo floatval($card['porcentaje_drop']); ?>', '<?php echo $desc_js; ?>', '<?php echo $rareza_js; ?>', <?php echo $cantidad; ?>, <?php echo $id_carta; ?>)">
                    
                    <div class="card-item-tipo" style="color: <?php echo ($card['rareza'] === 'Comun') ? '#94a3b8' : (($card['rareza'] === 'Epica') ? '#a855f7' : '#eab308'); ?>; display: flex; justify-content: space-between; width: 100%; box-sizing: border-box;">
                        <span><?php echo htmlspecialchars($card['rareza']); ?></span>
                        <span style="opacity: 0.7; font-size: 9px; font-weight: bold;">DROP: <?php echo floatval($card['porcentaje_drop']); ?>%</span>
                    </div>
                    
                    <div style="width: 100%; height: 120px; margin: 10px 0; background-color: #000; border-radius: 4px; overflow:hidden; position: relative;">
                        <img src="../../IM_CARTAS/<?php echo urlencode(basename($card['ruta_imagen_carta'])); ?>" alt="Carta" style="width:100%; height:100%; object-fit:cover;" onerror="this.src='../../IM_BUSQUEDA/LOGITO.jpg';">
                        <span style="position: absolute; bottom: 5px; right: 5px; background: rgba(0,0,0,0.8); color: #fff; padding: 2px 6px; font-size: 10px; border-radius: 3px; font-weight: bold; border: 1px solid #333;">x<?php echo $cantidad; ?></span>
                    </div>

                    <h4 class="card-item-title" style="margin: 5px 0;"><?php echo htmlspecialchars($card['nombre_personaje']); ?></h4>
                    <div style="font-size: 11px; font-weight: bold; display: flex; justify-content: space-around; background-color: #0c0c10; padding: 6px; border-radius: 4px;">
                        <span style="color: #ef4444;">ATK: <?php echo (intval($card['ataque']) >= 999999) ? 'INFINITO' : intval($card['ataque']); ?></span>
                        <span style="color: #3b82f6;">DEF: <?php echo (intval($card['defensa']) >= 999999) ? 'INFINITO' : intval($card['defensa']); ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #64748b; font-style: italic; text-align: center; width: 100%;">Aún no has descubierto cartas. Despliega portales en la sección de cofres para iniciar tu mazo.</p>
        <?php endif; ?>
      </div>
    </div>
  </main>


    <!-- VENTANA MODAL PARA EL DETALLE AMPLIADO CON PANEL DE TRADING STARK -->
  <div id="modal-detalle-carta" class="modal-carta" onclick="cerrarDetalleCartaExterno(event)">
      <div class="modal-carta-box">
          <span class="modal-carta-close" onclick="cerrarDetalleCarta()">&times;</span>
          <h2 id="modal-titulo" class="modal-carta-title-main">Nombre Personaje</h2>
          
          <div class="modal-carta-flex">
              <div class="modal-carta-img-box">
                  <img id="modal-imagen" src="" alt="Carta Ampliada">
              </div>
              <div class="modal-carta-side">
                  <div class="modal-stat-box">
                      <div class="modal-stat-label">Ataque</div>
                      <div id="modal-atk" class="modal-stat-value atk">0</div>
                  </div>
                  <div class="modal-stat-box">
                      <div class="modal-stat-label">Defensa</div>
                      <div id="modal-def" class="modal-stat-value def">0</div>
                  </div>
                  <div class="modal-stat-box">
                      <div class="modal-stat-label">Porcentaje Drop</div>
                      <div id="modal-drop" class="modal-stat-value drop">0%</div>
                  </div>
                  <div class="modal-stat-box">
                      <div class="modal-stat-label">Cantidad Poseida</div>
                      <div id="modal-cantidad" class="modal-stat-value" style="color: #10b981;">x0</div>
                  </div>
              </div>
          </div>
          
          <div id="modal-descripcion" class="modal-carta-desc">
              Descripción detallada.
          </div>

          <!-- SECCION DE REEMBOLSO INTEGRADA DIRECTO EN EL PANEL -->
          <div id="panel-venta-repetida" style="margin-top: 15px; display: none; padding: 12px; background-color: #1a1a24; border: 1px solid #333344; border-radius: 6px; justify-content: space-between; align-items: center; box-sizing: border-box;">
              <div>
                  <div style="font-size: 10px; color: #888888; text-transform: uppercase; font-weight: bold;">Reembolso Autorizado</div>
                  <div style="font-size: 14px; font-weight: bold; color: #eab308;"><span id="modal-precio-valor">0</span> Monedas</div>
              </div>
              <form action="perfil.php" method="POST" style="margin: 0;">
                  <input type="hidden" name="vender_carta_id" id="modal-input-vender-id" value="">
                  <button type="submit" class="btn-perfil-action" style="background-color: #ef4444; border-color: #dc2626; padding: 6px 12px; font-size: 12px;">Vender Copia</button>
              </form>
          </div>
      </div>
  </div>

  <script>
    function abrirPestana(evt, nombreTab) {
      const contenidos = document.getElementsByClassName("seccion-tab");
      for (let i = 0; i < contenidos.length; i++) {
        contenidos[i].classList.remove("activa");
      }
      const botones = document.getElementsByClassName("pestana-link");
      for (let i = 0; i < botones.length; i++) {
        botones[i].classList.remove("activa");
      }
      document.getElementById(nombreTab).classList.add("activa");
      evt.currentTarget.classList.add("activa");
    }

    // LISTADO ASOCIADO DE REEMBOLSOS LEGENDARIOS PERSONALIZADOS SEGÚN EL ID REAL DE CADA NAIPE
    const preciosLegendarios = {
        205: 250,  // Iron Man (Hulkbuster)
        206: 500,  // Thor (Rey de Asgard)
        210: 800,  // Wolverine (Berserker Gold)
        209: 1000, // Knull
        201: 1350, // Thanos (Guantelete)
        207: 2500, // Scarlet Witch desatada
        202: 2500, // Galactus
        203: 5500, // Fuerza Fénix
        211: 85000,// Loki Dios de las Historias
        208: 10000 // El Tribunal Viviente
    };

    function abrirDetalleCarta(nombre, img, atk, def, drop, descripcion, rareza, cantidad, idCarta) {
      document.getElementById('modal-titulo').innerText = nombre;
      document.getElementById('modal-imagen').src = img;
      document.getElementById('modal-atk').innerText = (parseInt(atk) >= 999999) ? 'INFINITO' : atk;
      document.getElementById('modal-def').innerText = (parseInt(def) >= 999999) ? 'INFINITO' : def;
      document.getElementById('modal-drop').innerText = drop + '%';
      document.getElementById('modal-cantidad').innerText = 'x' + cantidad;
      document.getElementById('modal-descripcion').innerText = descripcion;

      const panelVenta = document.getElementById('panel-venta-repetida');

      // EVALUACIÓN ECONÓMICA: Mostramos el panel si tiene al menos una copia extra repetida (cantidad > 1)
      if (parseInt(cantidad) > 1) {
          let precioReembolso = 25; // Común base
          if (rareza === 'Epica') precioReembolso = 100;
          if (rareza === 'Legendaria') {
              precioReembolso = preciosLegendarios[parseInt(idCarta)] ? preciosLegendarios[parseInt(idCarta)] : 300;
          }

          document.getElementById('modal-precio-valor').innerText = precioReembolso;
          document.getElementById('modal-input-vender-id').value = idCarta;
          panelVenta.style.display = 'flex';
      } else {
          panelVenta.style.display = 'none';
      }

      document.getElementById('modal-detalle-carta').style.display = 'flex';
    }

    function cerrarDetalleCarta() {
      document.getElementById('modal-detalle-carta').style.display = 'none';
    }

    function cerrarDetalleCartaExterno(event) {
      if (event.target === document.getElementById('modal-detalle-carta')) {
        cerrarDetalleCarta();
      }
    }
  </script>
</body>
</html>
<?php $conexion_perfil->close(); ?>
