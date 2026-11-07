
document.addEventListener('DOMContentLoaded', () => {
  const hero = document.querySelector('.hero-band');
  if (!hero) return;

  const mensaje = document.createElement('p');
  mensaje.textContent = 'BIENVENIDO AL UNIVERSO DE MARVEL';
  mensaje.style.cssText = [
    'position: relative',
    'margin: 18px 0 0',
    "font-family: var(--font-nav, 'Barlow Condensed', sans-serif)",
    'font-size: clamp(18px, 3vw, 28px)',
    'letter-spacing: 4px',
    'color: var(--red, #ed1d24)',
    'opacity: 0',
    'transition: opacity 1.2s ease'
  ].join(';');

  hero.appendChild(mensaje);

  
  requestAnimationFrame(() => {
    mensaje.style.opacity = '1';
  });
});
