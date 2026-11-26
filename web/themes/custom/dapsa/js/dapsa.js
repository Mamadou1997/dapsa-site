/**
 * @file
 * Carrousel des actualités de la page d'accueil.
 */
(function (Drupal, once) {
  Drupal.behaviors.dapsaCarrousel = {
    attach(context) {
      once('dapsa-carrousel', '.carrousel', context).forEach((carrousel) => {
        const diapos = carrousel.querySelectorAll('.carrousel__diapo');
        const position = carrousel.querySelector('.carrousel__position');
        if (diapos.length === 0) {
          return;
        }
        let courant = 0;
        const afficher = (i) => {
          diapos[courant].classList.remove('is-active');
          courant = (i + diapos.length) % diapos.length;
          diapos[courant].classList.add('is-active');
          if (position) {
            position.textContent = `${courant + 1} / ${diapos.length}`;
          }
        };
        diapos[0].classList.add('is-active');
        if (position) {
          position.textContent = `1 / ${diapos.length}`;
        }
        const prec = carrousel.querySelector('[data-carrousel="prec"]');
        const suiv = carrousel.querySelector('[data-carrousel="suiv"]');
        if (prec) {
          prec.addEventListener('click', () => afficher(courant - 1));
        }
        if (suiv) {
          suiv.addEventListener('click', () => afficher(courant + 1));
        }
      });
    },
  };
})(Drupal, once);
