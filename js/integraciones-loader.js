/**
 * integraciones-loader.js — CENEA
 * Lee data/integraciones.json e inyecta dinámicamente en el <head> y <body>:
 *  - Google Tag Manager (head + noscript body)
 *  - Google Analytics 4 (GA4)
 *  - Google Search Console verification
 *  - Meta Pixel (Facebook/Instagram Ads)
 *  - Google Ads conversion tracking
 *  - Hotjar
 *  - Microsoft Clarity
 *  - WhatsApp Business link actualizado
 *  - Código personalizado en head / body start / body end
 */
(function () {
    'use strict';

    const base   = document.querySelector('base')?.href || '';
    const jsonUrl = (base || '') + 'data/integraciones.json?v=' + Date.now();

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Inyecta un <script> externo en el <head> */
    function addScript(src, attrs) {
        const s = document.createElement('script');
        s.src   = src;
        s.async = true;
        if (attrs) Object.entries(attrs).forEach(([k, v]) => s.setAttribute(k, v));
        document.head.appendChild(s);
    }

    /** Inyecta un bloque <script> inline en el <head> */
    function addInlineScript(code, position) {
        const s = document.createElement('script');
        s.textContent = code;
        if (position === 'head' || !position) {
            document.head.appendChild(s);
        } else if (position === 'body-start') {
            document.body.insertBefore(s, document.body.firstChild);
        } else if (position === 'body-end') {
            document.body.appendChild(s);
        }
    }

    /** Inyecta HTML arbitrario en head o body */
    function addRawHTML(html, position) {
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        const nodes = Array.from(tmp.childNodes);
        nodes.forEach(node => {
            // Re-crear scripts para que se ejecuten
            if (node.nodeName === 'SCRIPT') {
                const s = document.createElement('script');
                if (node.src) s.src = node.src;
                else s.textContent = node.textContent;
                if (node.async) s.async = true;
                if (node.defer) s.defer = true;
                node = s;
            }
            if (position === 'head') {
                document.head.appendChild(node.cloneNode ? node.cloneNode(true) : node);
            } else if (position === 'body-start') {
                document.body.insertBefore(node.cloneNode ? node.cloneNode(true) : node, document.body.firstChild);
            } else {
                document.body.appendChild(node.cloneNode ? node.cloneNode(true) : node);
            }
        });
    }

    /** Agrega meta tag al head */
    function addMeta(name, content, attr) {
        if (!content) return;
        const m = document.createElement('meta');
        m.setAttribute(attr || 'name', name);
        m.setAttribute('content', content);
        document.head.appendChild(m);
    }

    // ── Integraciones ─────────────────────────────────────────────────────────

    function loadGTM(cfg) {
        if (!cfg.enabled || !cfg.container_id) return;
        const id = cfg.container_id.trim();

        // Head script
        addInlineScript(
            `(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':` +
            `new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],` +
            `j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;` +
            `j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;` +
            `f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${id}');`,
            'head'
        );

        // Body noscript (inmediatamente después del <body>)
        const ns = document.createElement('noscript');
        const iframe = document.createElement('iframe');
        iframe.src    = `https://www.googletagmanager.com/ns.html?id=${id}`;
        iframe.height = '0';
        iframe.width  = '0';
        iframe.style.cssText = 'display:none;visibility:hidden';
        ns.appendChild(iframe);
        document.body.insertBefore(ns, document.body.firstChild);
    }

    function loadGA4(cfg) {
        if (!cfg.enabled || !cfg.measurement_id) return;
        const id = cfg.measurement_id.trim();
        addScript(`https://www.googletagmanager.com/gtag/js?id=${id}`);
        addInlineScript(
            `window.dataLayer = window.dataLayer || [];` +
            `function gtag(){dataLayer.push(arguments);}` +
            `gtag('js', new Date());` +
            `gtag('config', '${id}');`,
            'head'
        );
    }

    function loadSearchConsole(cfg) {
        if (!cfg.enabled || !cfg.verification_code) return;
        addMeta('google-site-verification', cfg.verification_code.trim());
    }

    function loadMetaPixel(cfg) {
        if (!cfg.enabled || !cfg.pixel_id) return;
        const id = cfg.pixel_id.trim();
        addInlineScript(
            `!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?` +
            `n.callMethod.apply(n,arguments):n.queue.push(arguments)};` +
            `if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';` +
            `n.queue=[];t=b.createElement(e);t.async=!0;` +
            `t.src=v;s=b.getElementsByTagName(e)[0];` +
            `s.parentNode.insertBefore(t,s)}(window, document,'script',` +
            `'https://connect.facebook.net/en_US/fbevents.js');` +
            `fbq('init', '${id}');` +
            `fbq('track', 'PageView');`,
            'head'
        );
        // noscript pixel img
        const ns  = document.createElement('noscript');
        const img = document.createElement('img');
        img.height = '1';
        img.width  = '1';
        img.style.cssText = 'display:none';
        img.src = `https://www.facebook.com/tr?id=${id}&ev=PageView&noscript=1`;
        img.alt = '';
        ns.appendChild(img);
        document.head.appendChild(ns);
    }

    function loadGoogleAds(cfg) {
        if (!cfg.enabled || !cfg.conversion_id) return;
        const cid   = cfg.conversion_id.trim();
        const label = cfg.conversion_label ? cfg.conversion_label.trim() : '';
        addScript(`https://www.googletagmanager.com/gtag/js?id=${cid}`);
        addInlineScript(
            `window.dataLayer = window.dataLayer || [];` +
            `function gtag(){dataLayer.push(arguments);}` +
            `gtag('js', new Date());` +
            `gtag('config', '${cid}');` +
            (label ? `gtag('event', 'conversion', {'send_to': '${cid}/${label}'});` : ''),
            'head'
        );
    }

    function loadHotjar(cfg) {
        if (!cfg.enabled || !cfg.site_id) return;
        const id = cfg.site_id.trim();
        addInlineScript(
            `(function(h,o,t,j,a,r){` +
            `h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};` +
            `h._hjSettings={hjid:${id},hjsv:6};` +
            `a=o.getElementsByTagName('head')[0];` +
            `r=o.createElement('script');r.async=1;` +
            `r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;` +
            `a.appendChild(r);` +
            `})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');`,
            'head'
        );
    }

    function loadClarity(cfg) {
        if (!cfg.enabled || !cfg.project_id) return;
        const id = cfg.project_id.trim();
        addInlineScript(
            `(function(c,l,a,r,i,t,y){` +
            `c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};` +
            `t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;` +
            `y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);` +
            `})(window, document, "clarity", "script", "${id}");`,
            'head'
        );
    }

    function loadCustomHead(cfg) {
        if (!cfg.enabled || !cfg.code || !cfg.code.trim()) return;
        addRawHTML(cfg.code.trim(), 'head');
    }

    function loadCustomBodyStart(cfg) {
        if (!cfg.enabled || !cfg.code || !cfg.code.trim()) return;
        addRawHTML(cfg.code.trim(), 'body-start');
    }

    function loadCustomBodyEnd(cfg) {
        if (!cfg.enabled || !cfg.code || !cfg.code.trim()) return;
        addRawHTML(cfg.code.trim(), 'body-end');
    }

    // ── Fetch y dispatch ─────────────────────────────────────────────────────
    fetch(jsonUrl)
        .then(r => { if (!r.ok) throw new Error('integraciones.json no encontrado'); return r.json(); })
        .then(cfg => {
            // Orden importante: GTM primero, luego el resto
            loadGTM(cfg.google_tag_manager        || {});
            loadGA4(cfg.google_analytics           || {});
            loadSearchConsole(cfg.google_search_console || {});
            loadMetaPixel(cfg.meta_pixel           || {});
            loadGoogleAds(cfg.google_ads           || {});
            loadHotjar(cfg.hotjar                  || {});
            loadClarity(cfg.clarity                || {});
            loadCustomHead(cfg.custom_head         || {});
            loadCustomBodyStart(cfg.custom_body_start || {});
            loadCustomBodyEnd(cfg.custom_body_end  || {});
        })
        .catch(err => {
            console.warn('[integraciones-loader] No se pudo cargar:', err.message);
        });
})();
