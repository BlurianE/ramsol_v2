// Analytics: only loads on real domains (not localhost, IPs, .test, .local, file://)
(function() {
  var h = location.hostname;
  if (!h || h === 'localhost' || h === '127.0.0.1' || /^192\.168\./.test(h) || /^10\./.test(h) ||
      /\.(test|local)$/i.test(h) || location.protocol === 'file:') return;

  // Google Analytics GT-5MG33P4
  var gs = document.createElement('script');
  gs.async = true;
  gs.src = 'https://www.googletagmanager.com/gtag/js?id=GT-5MG33P4';
  document.head.appendChild(gs);
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'GT-5MG33P4', {linker: {domains: ['ramsol.com','ramsol.com.mx','ramsol.shop','zerone.mx']}});

  // Microsoft Clarity y8lpjt8m2u
  (function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
  t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;
  y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
  })(window,document,'clarity','script','y8lpjt8m2u');
})();
