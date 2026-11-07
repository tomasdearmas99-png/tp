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
// 1. ACCIÓN: SUBIR ARCHIVO LOCAL DESDE LA COMPUTADORA
// ========================================================
if (isset($_FILES['avatar_subido']) && $_FILES['avatar_subido']['error'] == 0) {
    $carpeta_destino = "../../IM_USUARIOS/";
    
    if (!file_exists($carpeta_destino)) {
        mkdir($carpeta_destino, 0777, true);
    }

    $extension = strtolower(pathinfo($_FILES['avatar_subido']['name'], PATHINFO_EXTENSION));
    $formatos_permitidos = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($extension, $formatos_permitidos)) {
        $nombre_final = "user_" . $id_usuario . "." . $extension;
        $ruta_completa = $carpeta_destino . $nombre_final;

        if (move_uploaded_file($_FILES['avatar_subido']['tmp_name'], $ruta_completa)) {
            $_SESSION['avatar_actual'] = $ruta_completa;
            $mensaje_consola = "¡Enlace biométrico establecido! Nueva foto de perfil cargada.";
        } else {
            $mensaje_consola = "Error al guardar el archivo en el servidor local XAMPP.";
        }
    } else {
        $mensaje_consola = "Formato inválido. Por favor sube una imagen JPG, PNG o WEBP.";
    }
}

// ========================================================
// 2. ACCIÓN: SELECCIONAR HÉROE PREDETERMINADO
// ========================================================
if (isset($_POST['avatar_url_predeterminado']) && !empty($_POST['avatar_url_predeterminado'])) {
    $_SESSION['avatar_actual'] = $_POST['avatar_url_predeterminado'];
    $mensaje_consola = "Foto de perfil actualizada con el héroe seleccionado.";
}

// ========================================================
// 3. ACCIÓN: EQUIPAR UN COSMÉTICO
// ========================================================
if (isset($_GET['equipar'])) {
    $id_item_equipar = intval($_GET['equipar']);
    $verificar = $conexion_perfil->query("SELECT categoria, nombre FROM inventario_cosmeticos ic INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item WHERE ic.id_usuario = $id_usuario AND ic.id_item = $id_item_equipar");
    
    if ($verificar && $verificar->num_rows > 0) {
        $item = $verificar->fetch_assoc();
        $categoria = $item['categoria'];
        $nombre_item = $item['nombre'];

        $conexion_perfil->query("UPDATE inventario_cosmeticos ic INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item SET ic.equipado = 0 WHERE ic.id_usuario = $id_usuario AND t.categoria = '$categoria'");
        $conexion_perfil->query("UPDATE inventario_cosmeticos SET equipado = 1 WHERE id_usuario = $id_usuario AND id_item = $id_item_equipar");
        
        $mensaje_consola = "¡Equipado con éxito!: " . $nombre_item;
    }
}

// CONSULTAS BASE DE DATOS
$datos_usuario = $conexion_perfil->query("SELECT username, monedas_marvel FROM usuarios WHERE id_usuario = $id_usuario")->fetch_assoc();

$avatar_final = "../../IM_BUSQUEDA/LOGITO.jpg";
if (isset($_SESSION['avatar_actual'])) {
    $avatar_final = $_SESSION['avatar_actual'];
}

$marco_equipado = ""; 
$titulo_equipado = "RECLUTA DE S.H.I.E.L.D.";

$consulta_equipados = $conexion_perfil->query("
    SELECT t.id_item, t.nombre, t.categoria 
    FROM inventario_cosmeticos ic 
    INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item 
    WHERE ic.id_usuario = $id_usuario AND ic.equipado = 1
");

while ($eq = $consulta_equipados->fetch_assoc()) {
    if ($eq['categoria'] === 'Marco') {
        if (intval($eq['id_item']) === 1) {
            $marco_equipado = "../../IM_COSMETICOS/Marco_Ojo_Halcon_Cosmetico.png";
        } else {
            $marco_equipado = "../../IM_COSMETICOS/item_" . $eq['id_item'] . ".png";
        }
    } elseif ($eq['categoria'] === 'Titulo') {
        $titulo_equipado = $eq['nombre'];
    }
}

$inventario = $conexion_perfil->query("
    SELECT t.id_item, t.nombre, t.categoria, ic.equipado 
    FROM inventario_cosmeticos ic 
    INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item 
    WHERE ic.id_usuario = $id_usuario
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

  <div class="perfil-cabecera">
    <div class="perfil-banner"></div>

    <div class="perfil-info-bloque">
      <div class="avatar-posicionador">
        <img src="<?php echo $avatar_final; ?>" alt="Avatar" class="avatar-imagen-core">
        <?php if (!empty($marco_equipado)): ?>
            <img src="<?php echo $marco_equipado; ?>" alt="Marco" class="avatar-marco-render">
        <?php endif; ?>
      </div>

      <h2 class="user-tag-name">@<?php echo htmlspecialchars($datos_usuario['username']); ?></h2>
      <p class="user-sub-status"><?php echo $titulo_equipado; ?></p>
      
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

    <!-- SECCIÓN 1: COSMÉTICOS -->
    <div id="tab-cosmeticos" class="seccion-tab activa">
      <div class="grid-items-inventario">
        <?php if ($inventario && $inventario->num_rows > 0): ?>
            <?php while($item = $inventario->fetch_assoc()): ?>
                <div class="card-item-propio <?php echo $item['equipado'] ? 'equipado-si' : ''; ?>">
                    <div class="card-item-tipo"><?php echo $item['categoria']; ?></div>
                    <h4 class="card-item-title"><?php echo $item['nombre']; ?></h4>
                    
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

    <!-- SECCIÓN 2: TARJETAS -->
    <div id="tab-tarjetas" class="seccion-tab">
      <div class="grid-items-inventario">
        <div class="card-item-propio" style="opacity: 0.3;">
            <div class="card-item-tipo">Carta Coleccionable</div>
            <h4 class="card-item-title">Iron Man (Mark LXXXV)</h4>
            <button class="btn-perfil-action" disabled>Bloqueada</button>
        </div>
      </div>
      <p style="color: #475569; font-size: 13px; text-align: center; margin-top: 24px; font-style: italic;">
        El módulo de colecciones se activará en la próxima actualización.
      </p>
    </div>
  </main>

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
  </script>
</body>
</html>
