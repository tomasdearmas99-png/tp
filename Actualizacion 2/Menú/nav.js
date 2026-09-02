
const menuToggle = document.querySelector('.menu-toggle');
const sidebar = document.querySelector('.sidebar');
const sidebarOverlay = document.querySelector('.sidebar-overlay');
const sidebarClose = document.querySelector('.sidebar__close');

menuToggle.addEventListener('click', () => {
  sidebar.classList.add('active');
  sidebarOverlay.classList.add('active');
  menuToggle.setAttribute('aria-expanded', 'true');
});

sidebarClose.addEventListener('click', () => {
  sidebar.classList.remove('active');
  sidebarOverlay.classList.remove('active');
  menuToggle.setAttribute('aria-expanded', 'false');
});


sidebarOverlay.addEventListener('click', () => {
  sidebar.classList.remove('active');
  sidebarOverlay.classList.remove('active');
  menuToggle.setAttribute('aria-expanded', 'false');
});