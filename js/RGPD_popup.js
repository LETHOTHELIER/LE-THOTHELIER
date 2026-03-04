// ========================================
// GESTION DU CONSENTEMENT AUX COOKIES RGPD
// ========================================

function setConsent(value) {
  try { localStorage.setItem('cookieConsent', value); }
  catch (e) { try { sessionStorage.setItem('cookieConsent', value); } catch (e2) {} }
}
function getConsent() {
  try { return localStorage.getItem('cookieConsent'); }
  catch (e) { try { return sessionStorage.getItem('cookieConsent'); } catch (e2) { return null; } }
}

function initCookieConsent() {
  console.log('Cookie Consent: Script chargé');

  const cookieConsent = getConsent();

  // Bloquer Google Maps immédiatement si refusé
  if (cookieConsent === 'refused') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', blockGoogleMaps);
    } else {
      blockGoogleMaps();
    }
  }

  // Styles responsive
  if (!document.getElementById('gdpr-responsive-styles')) {
    const s = document.createElement('style');
    s.id = 'gdpr-responsive-styles';
    s.textContent = `
      @media (max-width: 767px) {
        #gdpr-modal-overlay { padding: 10px !important; align-items: flex-start !important; padding-top: 40px !important; overflow-y: auto !important; }
        #privacy-notice-content { padding: 20px !important; width: 95% !important; max-width: 100% !important; margin-top: 0 !important; }
        #privacy-notice-content h3 { font-size: 20px !important; margin-bottom: 10px !important; }
        #privacy-notice-content p { font-size: 13px !important; line-height: 1.3 !important; margin: 8px 0 !important; }
        #privacy-actions { flex-direction: column !important; gap: 10px !important; margin: 15px 0 10px 0 !important; }
        #privacy-actions button { width: 100% !important; padding: 12px 20px !important; font-size: 14px !important; }
        #privacy-policy-link { font-size: 12px !important; margin-top: 5px !important; }
      }
      @media (max-width: 479px) {
        #gdpr-modal-overlay { padding-top: 30px !important; }
        #privacy-notice-content { padding: 15px !important; }
        #privacy-notice-content h3 { font-size: 18px !important; }
        #privacy-notice-content p { font-size: 12px !important; }
        #privacy-actions button { padding: 10px 15px !important; font-size: 13px !important; }
      }
    `;
    document.head.appendChild(s);
  }

  const isReading = sessionStorage.getItem('readingCookiePolicy');
  const onMentionsPage = !!document.getElementById('Mentionslegales');

  if (isReading === 'true') {
    if (onMentionsPage) {
      // Sur la page mentions légales : afficher les boutons inline, pas de popup
      initInlineButtons();
      return;
    } else {
      // Sur une autre page : l'utilisateur est parti sans choisir → popup + supprimer le flag
      sessionStorage.removeItem('readingCookiePolicy');
      if (cookieConsent === null) {
        showCookiePopup();
      }
      return;
    }
  }

  if (cookieConsent === null) {
    showCookiePopup();
  } else if (cookieConsent === 'refused') {
    blockGoogleMaps();
  }
}

// Initialise les boutons accepter/refuser dans la page mentions légales
function initInlineButtons() {
  const btnContainer = document.getElementById('cookie-choice-buttons');
  if (!btnContainer) return;

  btnContainer.style.display = 'block';

  const acceptBtn = document.getElementById('inline-accept');
  const refuseBtn = document.getElementById('inline-refuse');
  const confirmation = document.getElementById('choice-confirmation');

  if (acceptBtn) {
    acceptBtn.addEventListener('click', function() {
      setConsent('accepted');
      // Choix fait : supprimer le flag de lecture
      sessionStorage.removeItem('readingCookiePolicy');
      btnContainer.style.display = 'none';
      if (confirmation) {
        confirmation.style.display = 'block';
        confirmation.textContent = 'Cookies acceptés. Merci !';
      }
    });
  }

  if (refuseBtn) {
    refuseBtn.addEventListener('click', function() {
      setConsent('refused');
      // Choix fait : supprimer le flag de lecture
      sessionStorage.removeItem('readingCookiePolicy');
      blockGoogleMaps();
      btnContainer.style.display = 'none';
      if (confirmation) {
        confirmation.style.display = 'block';
        confirmation.textContent = 'Cookies refusés.';
      }
    });
  }

  // Si l'utilisateur quitte la page sans choisir : le flag reste → popup réapparaîtra
  // Pas de beforeunload nécessaire, sessionStorage persiste jusqu'à fermeture onglet
  // Mais on veut que le popup réapparaisse sur TOUTE autre page, pas juste à la fermeture
  // → on supprime le flag uniquement si l'utilisateur navigue HORS de mentions-legales sans choisir
  // Pour ça : on écoute les clics sur les liens qui quittent la page
  document.querySelectorAll('a[href]').forEach(function(a) {
    a.addEventListener('click', function() {
      const href = a.getAttribute('href');
      // Si le lien ne pointe pas vers mentions-legales, supprimer le flag
      // SEULEMENT si aucun choix n'a été fait (cookieConsent toujours null)
      if (href && !href.includes('mentions-legales') && getConsent() === null) {
        // On garde le flag → le popup s'affichera sur la page suivante
        // (ne rien faire, sessionStorage reste)
      }
    });
  });
}

function hidePopup() {
  const popup = document.getElementById('gdpr-modal-overlay');
  if (popup) {
    popup.style.opacity = '0';
    setTimeout(function() { popup.remove(); }, 300);
  }
}

function blockGoogleMaps() {
  const iframes = document.querySelectorAll('iframe[src*="google.com/maps"]');
  iframes.forEach(function(iframe) {
    const replacement = document.createElement('div');
    replacement.style.cssText = 'background: #1a1a1a; border: 3px solid #b86e44; border-radius: 10px; padding: 40px; text-align: center; min-height: 400px; display: flex; align-items: center; justify-content: center;';
    const content = document.createElement('div');
    const h4 = document.createElement('h4');
    h4.textContent = 'Google Maps bloqué';
    h4.style.cssText = 'color: #b86e44 !important; font-family: Verdana, Geneva, sans-serif !important; font-size: 24px !important; font-weight: 900 !important; margin: 0 0 15px 0 !important; text-shadow: 3px 2px 4px black !important;';
    const p1 = document.createElement('p');
    p1.textContent = 'Vous avez refusé les cookies Google Maps.';
    p1.style.cssText = 'color: #d9d9d9 !important; font-family: "Montserrat", sans-serif !important; font-size: 16px !important; font-weight: 900 !important; margin: 10px 0 !important; text-shadow: 2px 1px 3px black !important;';
    const p2 = document.createElement('p');
    p2.textContent = 'Pour afficher la carte, veuillez accepter les cookies.';
    p2.style.cssText = 'color: #d9d9d9 !important; font-family: "Montserrat", sans-serif !important; font-size: 16px !important; font-weight: 900 !important; margin: 10px 0 !important; text-shadow: 2px 1px 3px black !important;';
    const btn = document.createElement('button');
    btn.textContent = 'Accepter les cookies';
    btn.style.cssText = 'font-family: "Montserrat", sans-serif !important; font-size: 16px !important; font-weight: 900 !important; padding: 15px 40px !important; border: 2px solid #b86e44 !important; border-radius: 5px !important; cursor: pointer !important; background: #b86e44 !important; color: #1a1a1a !important; text-shadow: 2px 1px 3px black !important; margin-top: 20px !important;';
    btn.onclick = function() { setConsent('accepted'); location.reload(); };
    content.appendChild(h4); content.appendChild(p1); content.appendChild(p2); content.appendChild(btn);
    replacement.appendChild(content);
    iframe.parentNode.replaceChild(replacement, iframe);
  });
}

function showCookiePopup() {
  const popup = document.createElement('div');
  popup.id = 'gdpr-modal-overlay';
  popup.style.cssText = 'position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; z-index: 999999 !important; display: flex !important; align-items: center !important; justify-content: center !important; opacity: 1 !important; visibility: visible !important;';

  const overlay = document.createElement('div');
  overlay.id = 'gdpr-backdrop';
  overlay.style.cssText = 'position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background: rgba(0,0,0,0.85) !important; z-index: 999998 !important; display: block !important;';

  const box = document.createElement('div');
  box.id = 'privacy-notice-content';
  box.style.cssText = 'background: #1a1a1a !important; border: 3px solid #b86e44 !important; border-radius: 10px !important; padding: 40px !important; max-width: 600px !important; width: 90% !important; box-shadow: 0 0 30px rgba(184,110,68,0.5) !important; text-align: center !important; position: relative !important; z-index: 1000000 !important; display: block !important; visibility: visible !important; opacity: 1 !important;';

  const title = document.createElement('h3');
  title.textContent = 'Utilisation des cookies';
  title.style.cssText = 'color: #b86e44 !important; font-family: Verdana, Geneva, sans-serif !important; font-size: 32px !important; font-weight: 900 !important; margin: 0 0 20px 0 !important; text-shadow: 3px 2px 4px black !important;';

  const p1 = document.createElement('p');
  p1.textContent = "Ce site utilise Google Maps pour faciliter la localisation de l'atelier, ainsi que Google Analytics et Google Tag Manager pour mesurer l'audience et améliorer votre expérience. Ces services Google peuvent déposer des cookies et collecter certaines données de navigation (adresse IP, pages visitées, localisation approximative).";
  p1.style.cssText = 'color: #d9d9d9 !important; font-family: "Montserrat", sans-serif !important; font-size: 16px !important; font-weight: 900 !important; line-height: 1.6 !important; margin: 15px 0 !important; text-shadow: 2px 1px 3px black !important;';

  const p2 = document.createElement('p');
  p2.textContent = "Vous pouvez accepter ou refuser ces cookies. En cas de refus, Google Maps ne s'affichera pas et les statistiques de visite ne seront pas collectées.";
  p2.style.cssText = 'color: #d9d9d9 !important; font-family: "Montserrat", sans-serif !important; font-size: 16px !important; font-weight: 900 !important; line-height: 1.6 !important; margin: 15px 0 !important; text-shadow: 2px 1px 3px black !important;';

  const buttonsDiv = document.createElement('div');
  buttonsDiv.id = 'privacy-actions';
  buttonsDiv.style.cssText = 'display: flex !important; gap: 20px !important; justify-content: center !important; margin: 30px 0 20px 0 !important; flex-wrap: wrap !important;';

  const acceptBtn = document.createElement('button');
  acceptBtn.id = 'allow-analytics';
  acceptBtn.textContent = 'Accepter';
  acceptBtn.style.cssText = 'font-family: "Montserrat", sans-serif !important; font-size: 16px !important; font-weight: 900 !important; padding: 15px 40px !important; border: 2px solid #b86e44 !important; border-radius: 5px !important; cursor: pointer !important; background: #b86e44 !important; color: #1a1a1a !important; text-shadow: 2px 1px 3px black !important;';

  const refuseBtn = document.createElement('button');
  refuseBtn.id = 'deny-analytics';
  refuseBtn.textContent = 'Refuser';
  refuseBtn.style.cssText = 'font-family: "Montserrat", sans-serif !important; font-size: 16px !important; font-weight: 900 !important; padding: 15px 40px !important; border: 2px solid #b86e44 !important; border-radius: 5px !important; cursor: pointer !important; background: transparent !important; color: #d9d9d9 !important; text-shadow: 2px 1px 3px black !important;';

  const link = document.createElement('a');
  link.href = '/mentions-legales.html?show-cookie-choice=true#icsflf';
  link.id = 'privacy-policy-link';
  link.textContent = 'En savoir plus sur les cookies';
  link.style.cssText = 'color: #b86e44 !important; font-family: "Montserrat", sans-serif !important; font-size: 14px !important; font-weight: 900 !important; text-decoration: underline !important; display: inline-block !important; margin-top: 10px !important;';

  buttonsDiv.appendChild(acceptBtn);
  buttonsDiv.appendChild(refuseBtn);
  box.appendChild(title); box.appendChild(p1); box.appendChild(p2);
  box.appendChild(buttonsDiv); box.appendChild(link);
  popup.appendChild(overlay); popup.appendChild(box);

  const target = document.getElementById('Index') || document.getElementById('Mentionslegales') || document.getElementById('Contact') || document.getElementById('Galerie') || document.body;
  target.appendChild(popup);

  acceptBtn.addEventListener('click', function() {
    setConsent('accepted');
    sessionStorage.removeItem('readingCookiePolicy');
    hidePopup();
  });

  refuseBtn.addEventListener('click', function() {
    setConsent('refused');
    sessionStorage.removeItem('readingCookiePolicy');
    blockGoogleMaps();
    hidePopup();
  });

  link.addEventListener('click', function(e) {
    e.preventDefault();
    // Marquer que l'utilisateur va lire la politique SANS avoir fait de choix
    sessionStorage.setItem('readingCookiePolicy', 'true');
    hidePopup();
    setTimeout(function() {
      window.location.href = '/mentions-legales.html?show-cookie-choice=true#icsflf';
    }, 100);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCookieConsent);
} else {
  initCookieConsent();
}
