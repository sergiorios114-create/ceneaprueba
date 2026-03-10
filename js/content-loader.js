/**
 * content-loader.js — Cenea
 * Carga el contenido dinámico desde data/site_content.json
 * y lo inyecta en los elementos con atributo [data-content="page.field"]
 */
document.addEventListener('DOMContentLoaded', () => {

    // ── Sanitización XSS: whitelist de tags y atributos seguros ──────────────
    // Permite HTML de formato visual (negrita, cursiva, color) pero bloquea
    // scripts, iframes, eventos on*, y cualquier tag no permitido.
    const ALLOWED_TAGS  = ['strong','em','b','i','u','br','span','p','mark','s','sub','sup'];
    const ALLOWED_ATTRS = ['style', 'class'];

    function safeHTML(dirty) {
        if (!dirty || typeof dirty !== 'string') return '';
        const tmp = document.createElement('div');
        tmp.innerHTML = dirty;

        // Recorrer todos los elementos y filtrar
        tmp.querySelectorAll('*').forEach(node => {
            const tag = node.tagName.toLowerCase();

            // Si el tag no está en la whitelist, reemplazar por su texto plano
            if (!ALLOWED_TAGS.includes(tag)) {
                node.replaceWith(document.createTextNode(node.textContent));
                return;
            }

            // Eliminar atributos no permitidos o peligrosos
            Array.from(node.attributes).forEach(attr => {
                const name = attr.name.toLowerCase();
                // Bloquear event handlers (onclick, onload, etc.)
                if (name.startsWith('on')) {
                    node.removeAttribute(attr.name);
                    return;
                }
                // Bloquear atributos no en la whitelist
                if (!ALLOWED_ATTRS.includes(name)) {
                    node.removeAttribute(attr.name);
                    return;
                }
                // Bloquear javascript: dentro de style
                if (name === 'style' && /javascript\s*:/i.test(attr.value)) {
                    node.removeAttribute('style');
                }
            });
        });

        return tmp.innerHTML;
    }

    // ── Carga del JSON ────────────────────────────────────────────────────────
    const base = document.querySelector('base')?.href || '';
    const jsonUrl = base ? base + 'data/site_content.json' : 'data/site_content.json';

    fetch(jsonUrl + '?v=' + Date.now()) // cache-bust
        .then(response => {
            if (!response.ok) throw new Error('No se pudo cargar el contenido');
            return response.json();
        })
        .then(data => {
            const elements = document.querySelectorAll('[data-content]');

            elements.forEach(el => {
                const key = el.dataset.content; // "home.hero_title"
                const parts = key.split('.');
                if (parts.length < 2) return;

                const page  = parts[0];
                const field = parts.slice(1).join('.'); // soporta claves compuestas

                const value = data?.[page]?.[field];
                if (value === undefined || value === null || value === '') return;

                if (el.tagName === 'IMG') {
                    // Imagen: actualizar src
                    el.src = value;
                } else if (el.tagName === 'A' && (field.includes('link') || field.includes('url'))) {
                    // Link: actualizar href
                    el.href = value;
                } else {
                    // Texto: sanitizar antes de inyectar (permite <em>, <strong>, <br>, colores)
                    el.innerHTML = safeHTML(value);
                }
            });

            // Soporte para data-content-href: actualiza el atributo href sin tocar el texto del enlace
            const hrefElements = document.querySelectorAll('[data-content-href]');
            hrefElements.forEach(el => {
                const key = el.dataset.contentHref;
                const parts = key.split('.');
                if (parts.length < 2) return;
                const page  = parts[0];
                const field = parts.slice(1).join('.');
                const value = data?.[page]?.[field];
                if (value) el.href = value;
            });
        })
        .catch(error => {
            console.warn('[content-loader] Error al cargar site_content.json:', error.message);
        });
});
