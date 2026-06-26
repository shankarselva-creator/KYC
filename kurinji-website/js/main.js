// Mobile nav toggle
const hamburger = document.querySelector('.hamburger');
const navMenu = document.querySelector('nav ul');
if (hamburger && navMenu) {
  hamburger.addEventListener('click', () => {
    navMenu.classList.toggle('open');
    hamburger.classList.toggle('active');
  });
}

// Active nav link
const currentPage = location.pathname.split('/').pop() || 'index.html';
document.querySelectorAll('nav ul li a').forEach(link => {
  if (link.getAttribute('href') === currentPage) link.classList.add('active');
});

// Menu tabs
document.querySelectorAll('.menu-tab').forEach(tab => {
  tab.addEventListener('click', () => {
    document.querySelectorAll('.menu-tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    const category = tab.dataset.category;
    document.querySelectorAll('.menu-card').forEach(card => {
      card.style.display = (category === 'all' || card.dataset.category === category) ? '' : 'none';
    });
  });
});

// Simple form validation feedback
document.querySelectorAll('form').forEach(form => {
  form.addEventListener('submit', e => {
    e.preventDefault();
    const btn = form.querySelector('[type="submit"]');
    const original = btn.textContent;
    btn.textContent = '✓ Submitted! We\'ll confirm shortly.';
    btn.disabled = true;
    btn.style.background = '#2e7d32';
    setTimeout(() => { btn.textContent = original; btn.disabled = false; btn.style.background = ''; }, 4000);
  });
});

// Smooth reveal on scroll
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.1 });
document.querySelectorAll('.menu-card, .special-card, .testimonial-card, .value-card, .team-card').forEach(el => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(20px)';
  el.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
  observer.observe(el);
});
document.addEventListener('animationend', () => {});
// Trigger visible class
const styleSheet = document.createElement('style');
styleSheet.textContent = '.visible { opacity: 1 !important; transform: translateY(0) !important; }';
document.head.appendChild(styleSheet);
