(function () {
  function copyText(text, button) {
    var img = button.querySelector('img');

    function showCopied() {
      if (img) {
        img.style.opacity = '0.3';
        setTimeout(function () { img.style.opacity = ''; }, 1200);
      }

      var feedback = document.createElement('span');
      feedback.textContent = 'Copied';
      feedback.style.cssText = 'margin-left:4px;font-size:11px;color:#2271b1;';
      button.insertAdjacentElement('afterend', feedback);
      setTimeout(function () { feedback.remove(); }, 1200);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(showCopied);
      return;
    }

    var textarea = document.createElement('textarea');
    textarea.value = text;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    showCopied();
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-oliforge-polls-copy-shortcode]');
    if (!button) return;
    event.preventDefault();
    copyText(button.getAttribute('data-oliforge-polls-copy-shortcode') || '', button);
  });
})();
