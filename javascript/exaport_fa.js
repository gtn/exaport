// The script to limit using of Fontawesome icons only for special html blocks. not for whole document

// Disable auto-replacement globally
window.FontAwesomeConfig = {
  autoReplaceSvg: false
};

document.addEventListener('DOMContentLoaded', function () {
  var mainBlock = $('#exaport');
  block_exaport_update_fontawesome_icons(mainBlock);
});

