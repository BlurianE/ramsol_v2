// If opened as file:// and the path contains site_ramsol in htdocs, redirect to localhost
(function() {
  if (location.protocol === 'file:') {
    var path = location.pathname;
    var match = path.match(/htdocs[/\\](site_ramsol[/\\].*)$/i);
    if (match) {
      location.replace('http://localhost/' + match[1].replace(/\\/g, '/'));
      return;
    }
    // If not in htdocs, disable YouTube iframes (Error 153 workaround)
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('iframe[src*="youtube"]').forEach(function(iframe) {
        var id = (iframe.src.match(/embed\/([\w-]+)/) || [])[1];
        if (!id) return;
        var img = document.createElement('img');
        img.src = 'https://img.youtube.com/vi/' + id + '/hqdefault.jpg';
        img.alt = 'Video de YouTube';
        img.className = 'noLightbox';
        img.style.cssText = 'width:100%;height:auto;cursor:pointer;';
        img.onclick = function() { window.open('https://youtube.com/watch?v=' + id); };
        iframe.parentNode.replaceChild(img, iframe);
      });
    });
  }
})();
