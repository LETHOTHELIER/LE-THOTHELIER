// ========================================
// EFFET PARALLAXE SMOOTH
// ========================================

(function() {
  'use strict';
  
  // Fonction pour l'effet parallaxe
  function parallaxEffect() {
    const parallaxSections = document.querySelectorAll('.parallax-section');
    const scrolled = window.pageYOffset;
    
    parallaxSections.forEach(function(section) {
      const image = section.querySelector('.parallax-image');
      if (!image) return;
      
      // Calculer la position de la section
      const rect = section.getBoundingClientRect();
      const sectionTop = rect.top + scrolled;
      const windowHeight = window.innerHeight;
      
      // Si la section est visible dans le viewport
      if (rect.top < windowHeight && rect.bottom > 0) {
        // Calculer le pourcentage de scroll de la section
        const scrollPercent = (scrolled + windowHeight - sectionTop) / (section.offsetHeight + windowHeight);
        
        // Appliquer un déplacement plus lent (effet parallaxe)
        // La valeur négative fait bouger l'image vers le haut
        const yPos = -(scrollPercent * 100 - 50);
        
        image.style.transform = 'translate3d(0, ' + yPos + '%, 0)';
      }
    });
  }
  
  // Appliquer l'effet au scroll (avec throttle pour performance)
  let ticking = false;
  
  window.addEventListener('scroll', function() {
    if (!ticking) {
      window.requestAnimationFrame(function() {
        parallaxEffect();
        ticking = false;
      });
      ticking = true;
    }
  });
  
  // Appliquer l'effet au chargement
  window.addEventListener('load', parallaxEffect);
  
  // Appliquer l'effet au redimensionnement
  window.addEventListener('resize', parallaxEffect);
  
})();