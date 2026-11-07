// Cartel de iniciar sesión / registrarse. Se muestra si no hay sesión activa.
(function () {
  // Ruta de PHP calculada desde la ubicación de este script (web/JS/ -> web/PHP/)
  const API = new URL('../PHP/', document.currentScript.src).href;

  async function post(archivo, datos) {
    const r = await fetch(API + archivo, { method: 'POST', body: new URLSearchParams(datos) });
    return r.json();
  }

  function crearModal() {
    const overlay = document.createElement('div');
    overlay.className = 'auth-overlay';
    overlay.innerHTML = `
      <div class="auth-box" role="dialog" aria-modal="true" aria-labelledby="auth-titulo">
        <h2 id="auth-titulo">MARVEL</h2>
        <div class="auth-tabs">
          <button type="button" class="auth-tab is-active" data-tab="login">Iniciar sesión</button>
          <button type="button" class="auth-tab" data-tab="registro">Crear cuenta</button>
        </div>

        <div class="auth-panel" data-panel="login">
          <input id="login-id" type="text" placeholder="Usuario o email" autocomplete="username">
          <input id="login-pass" type="password" placeholder="Contraseña" autocomplete="current-password">
          <button type="button" class="auth-btn" id="btn-login">Entrar</button>
        </div>

        <div class="auth-panel" data-panel="registro" hidden>
          <input id="reg-user" type="text" placeholder="Nombre de usuario" autocomplete="username">
          <input id="reg-email" type="email" placeholder="Email" autocomplete="email">
          <input id="reg-pass" type="password" placeholder="Contraseña (mín. 6 caracteres)" autocomplete="new-password">
          <button type="button" class="auth-btn" id="btn-registro">Registrarme</button>
        </div>

        <p class="auth-msg" id="auth-msg"></p>
      </div>`;
    document.body.appendChild(overlay);
    document.body.classList.add('auth-bloqueado');

    const msg = overlay.querySelector('#auth-msg');
    const decir = (t, ok) => { msg.textContent = t; msg.className = 'auth-msg ' + (ok ? 'ok' : 'error'); };

    overlay.querySelectorAll('.auth-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        overlay.querySelectorAll('.auth-tab').forEach(t => t.classList.toggle('is-active', t === tab));
        overlay.querySelectorAll('.auth-panel').forEach(p => p.hidden = p.dataset.panel !== tab.dataset.tab);
        decir('', true);
      });
    });

    async function enviar(archivo, datos) {
      try {
        const res = await post(archivo, datos);
        decir(res.mensaje, res.ok);
        if (res.ok) setTimeout(() => location.reload(), 700);
      } catch (e) {
        decir('Error de conexión con el servidor.', false);
      }
    }

    overlay.querySelector('#btn-login').addEventListener('click', () =>
      enviar('login.php', {
        identificador: overlay.querySelector('#login-id').value,
        password: overlay.querySelector('#login-pass').value
      }));

    overlay.querySelector('#btn-registro').addEventListener('click', () =>
      enviar('registro.php', {
        username: overlay.querySelector('#reg-user').value,
        email: overlay.querySelector('#reg-email').value,
        password: overlay.querySelector('#reg-pass').value
      }));

    overlay.addEventListener('keydown', e => {
      if (e.key !== 'Enter') return;
      const panel = overlay.querySelector('.auth-panel:not([hidden]) .auth-btn');
      if (panel) panel.click();
    });
  }

  function mostrarUsuario(u) {
    const nav = document.querySelector('.topbar__links');
    if (!nav) return;
    const span = document.createElement('span');
    span.className = 'auth-usuario';
    span.innerHTML = `${u.username} · 🪙 ${u.monedas} <a href="#" id="btn-logout">Salir</a>`;
    nav.appendChild(span);
    span.querySelector('#btn-logout').addEventListener('click', async e => {
      e.preventDefault();
      await post('logout.php', {});
      location.reload();
    });
  }

  // Mientras la página está abierta, avisa al servidor cada 30 s que seguís en la web.
  // Al cerrarla, el servidor cuenta los 5 minutos desde ese último aviso.
  function mantenerSesion() {
    const ping = async () => {
      try {
        const r = await fetch(API + 'sesion.php', { cache: 'no-store' });
        const s = await r.json();
        if (!s.logueado) location.reload(); // la sesión venció: se muestra el cartel
      } catch (e) {}
    };
    setInterval(ping, 30000);
    window.addEventListener('pagehide', () => navigator.sendBeacon(API + 'sesion.php'));
  }

  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const r = await fetch(API + 'sesion.php');
      const s = await r.json();
      if (s.logueado) { mostrarUsuario(s); mantenerSesion(); } else crearModal();
    } catch (e) {
      console.error('No se pudo comprobar la sesión:', e);
    }
  });
})();