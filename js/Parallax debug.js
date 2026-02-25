// Debug script - à ajouter temporairement pour voir ce qui se passe
console.log('=== PARALLAX DEBUG ===');

// Vérifier que les sections parallaxe existent
const parallaxSections = document.querySelectorAll('.parallax-section');
console.log('Nombre de sections parallaxe:', parallaxSections.length);

// Vérifier chaque section
parallaxSections.forEach((section, index) => {
  const image = section.querySelector('.parallax-image');
  const dataImage = section.getAttribute('data-image');
  const bgImage = image ? window.getComputedStyle(image).backgroundImage : 'none';
  
  console.log(`Section ${index + 1}:`);
  console.log('  - data-image:', dataImage);
  console.log('  - background-image:', bgImage);
  console.log('  - Visible:', image ? 'OUI' : 'NON');
});

// Vérifier que les sections principales n'ont plus de background
const sections = ['Accueil', 'APropos_rubrique', 'Prestations_rubrique', 'TarifsRubrique'];
sections.forEach(id => {
  const el = document.getElementById(id);
  if (el) {
    const bgImage = window.getComputedStyle(el).backgroundImage;
    console.log(`#${id} background:`, bgImage);
  }
});