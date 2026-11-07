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
// 1. ACCIÓN CENTRALIZADA: EQUIPAR (MARCO, TÍTULO O FONDO)
// ========================================================
if (isset($_GET['equipar'])) {
    $id_item_equipar = intval($_GET['equipar']);
    
    // Verificamos la categoría real del cosmético que el usuario quiere equipar
    $verificar = $conexion_perfil->query("SELECT categoria, nombre FROM tienda_cosmeticos WHERE id_item = $id_item_equipar");
    
    if ($verificar && $verificar->num_rows > 0) {
        $item = $verificar->fetch_assoc();
        $categoria = $item['categoria'];
        $nombre_item = $item['nombre'];

        // 1. Desequipar cualquier cosmético de la MISMA categoría para este usuario
        $conexion_perfil->query("UPDATE inventario_cosmeticos ic INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item SET ic.equipado = 0 WHERE ic.id_usuario = $id_usuario AND t.categoria = '$categoria'");
        
        // 2. Equipar el cosmético seleccionado (poner equipado = 1)
        $conexion_perfil->query("UPDATE inventario_cosmeticos SET equipado = 1 WHERE id_usuario = $id_usuario AND id_item = $id_item_equipar");
        
        $mensaje_consola = "¡Protocolo activado! Has equipado: " . $nombre_item;
    }
}

// ========================================================
// 2. ACCIÓN: SELECCIONAR HÉROE PREDETERMINADO
// ========================================================
if (isset($_POST['avatar_url_predeterminado']) && !empty($_POST['avatar_url_predeterminado'])) {
    $_SESSION['avatar_actual'] = $_POST['avatar_url_predeterminado'];
    $mensaje_consola = "Avatar de perfil sincronizado.";
}

// ========================================================
// 3. CONSULTAS GENERALES DE RENDERIZADO
// ========================================================
$datos_usuario = $conexion_perfil->query("SELECT username, monedas_marvel FROM usuarios WHERE id_usuario = $id_usuario")->fetch_assoc();

$avatar_final = "../../IM_BUSQUEDA/LOGITO.jpg";
if (isset($_SESSION['avatar_actual'])) {
    $avatar_final = $_SESSION['avatar_actual'];
}

// Valores por defecto si el usuario no tiene nada equipado
$marco_equipado = ""; 
$titulo_equipado = "RECLUTA DE S.H.I.E.L.D.";
$fondo_equipado_url = ""; 

// Buscar todos los cosméticos equipados actualmente de forma dinámica
$consulta_equipados = $conexion_perfil->query("
    SELECT t.id_item, t.nombre, t.categoria 
    FROM inventario_cosmeticos ic 
    INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item 
    WHERE ic.id_usuario = $id_usuario AND ic.equipado = 1
");

if ($consulta_equipados) {
    while ($eq = $consulta_equipados->fetch_assoc()) {
        if ($eq['categoria'] === 'Marco') {
            if (intval($eq['id_item']) === 1) {
                $marco_equipado = "../../IM_COSMETICOS/Marco_Ojo_Halcon_Cosmetico.png";
            } else {
                $marco_equipado = "../../IM_COSMETICOS/item_" . $eq['id_item'] . ".png";
            }
        } elseif ($eq['categoria'] === 'Titulo') {
            $titulo_equipado = $eq['nombre'];
        } elseif ($eq['categoria'] === 'Fondo') {
            // Carga la ruta de la imagen correspondiente de tu tienda (ej: item_5.png)
            $fondo_equipado_url = "../../IM_COSMETICOS/item_" . $eq['id_item'] . ".png";
        }
    }
}

// Cargar todo el inventario de cosméticos comprados (marcos, títulos, fondos)
$inventario = $conexion_perfil->query("
    SELECT t.id_item, t.nombre, t.categoria, ic.equipado 
    FROM inventario_cosmeticos ic 
    INNER JOIN tienda_cosmeticos t ON ic.id_item = t.id_item 
    WHERE ic.id_usuario = $id_usuario
");

// Cargar mazo de cartas obtenidas del cofre
$mis_cartas = $conexion_perfil->query("
    SELECT c.nombre_personaje, c.rareza, c.ataque, c.defensa, c.ruta_imagen_carta 
    FROM usuario_cartas uc 
    INNER JOIN cartas c ON uc.id_carta = c.id_carta 
    WHERE uc.id_usuario = $id_usuario
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

  <!-- BANNER SUPERIOR DINÁMICO: Si hay un fondo equipado, lo renderiza como imagen de fondo -->
  <div class="perfil-cabecera">
    <div class="perfil-banner" style="<?php echo !empty($fondo_equipado_url) ? "background-image: url('$fondo_equipado_url'); background-size: cover; background-position: center;" : ""; ?>"></div>

    <div class="perfil-info-bloque">
      <div class="avatar-posicionador">
        <img src="<?php echo $avatar_final; ?>" alt="Avatar" class="avatar-imagen-core">
        <?php if (!empty($marco_equipado)): ?>
            <img src="<?php echo $marco_equipado; ?>" alt="Marco" class="avatar-marco-render">
        <?php endif; ?>
      </div>

      <h2 class="user-tag-name">@<?php echo htmlspecialchars($datos_usuario['username']); ?></h2>
      <p class="user-sub-status"><?php echo htmlspecialchars($titulo_equipado); ?></p>
      
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

    <!-- PESTAÑA 1: COSMÉTICOS (MUESTRA MARCOS, TÍTULOS Y FONDOS COMPRADOS) -->
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
                <div class="card-item-propio" style="border-left: 4px solid <?php echo ($card['rareza'] === 'Comun') ? '#475569' : (($card['rareza'] === 'Epica') ? '#7c3aed' : '#ca8a04'); ?>;">
                    <div class="card-item-tipo" style="color: <?php echo ($card['rareza'] === 'Comun') ? '#94a3b8' : (($card['rareza'] === 'Epica') ? '#a855f7' : '#eab308'); ?>;">
                        <?php echo htmlspecialchars($card['rareza']); ?>
                    </div>
                    
                    <div style="width: 100%; height: 120px; margin: 10px 0; background-color: #000; border-radius: 4px; overflow:hidden;">
                        <img src="<?php echo htmlspecialchars($card['ruta_imagen_carta']); ?>" alt="Carta" style="width:100%; height:100%; object-fit:cover;" onerror="this.src='../../IM_BUSQUEDA/LOGITO.jpg';">
                    </div>

                    <h4 class="card-item-title" style="margin: 5px 0;"><?php echo htmlspecialchars($card['nombre_personaje']); ?></h4>
                    <div style="font-size: 11px; font-weight: bold; display: flex; justify-content: space-around; background-color: #0c0c10; padding: 6px; border-radius: 4px;">
                        <span style="color: #ef4444;"> ATK: <?php echo intval($card['ataque']); ?></span>
                        <span style="color: #3b82f6;"> DEF: <?php echo intval($card['defensa']); ?></span>
                    </div>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #64748b; font-style: italic; text-align: center; width: 100%;">Aún no has descubierto cartas. Despliega portales en la sección de cofres para iniciar tu mazo.</p>
        <?php endif; ?>
      </div>
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
<?php $conexion_perfil->close(); ?>
