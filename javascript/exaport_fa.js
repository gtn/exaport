// The script to limit using of Fontawesome icons only for special html blocks. not for whole document

// Disable auto-replacement globally
window.FontAwesomeConfig = {
  autoReplaceSvg: false
};

document.addEventListener('DOMContentLoaded', function () {
  require(['block_exaport/competence_badges'], function (CompetenceBadges) {
    CompetenceBadges.initialise(document.getElementById('exaport'));
  });
});

