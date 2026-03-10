/**
 * seo-injector.js — CENEA
 * Lee data/seo.json e inyecta dinámicamente en cada página:
 *  - <title>
 *  - Meta description, robots, canonical
 *  - Open Graph (og:*)
 *  - Twitter Cards
 *  - JSON-LD Schemas: Organization, LocalBusiness, Breadcrumb, página específica
 *  - theme-color
 */
(function () {
    'use strict';

    // ── Detectar qué página es ──────────────────────────────────────────────
    const path = window.location.pathname;
    const filename = path.split('/').pop().replace('.html', '') || 'index';
    const PAGE_KEY = filename === '' ? 'index' : filename;

    // ── Ruta al JSON (soporta subdirectorios en SiteGround) ─────────────────
    const base = document.querySelector('base')?.href || '';
    const jsonUrl = (base || '') + 'data/seo.json?v=' + Date.now();

    // ── Helpers ─────────────────────────────────────────────────────────────
    function setMeta(name, content, attr = 'name') {
        if (!content) return;
        let el = document.querySelector(`meta[${attr}="${name}"]`);
        if (!el) {
            el = document.createElement('meta');
            el.setAttribute(attr, name);
            document.head.appendChild(el);
        }
        el.setAttribute('content', content);
    }

    function setLink(rel, href) {
        if (!href) return;
        let el = document.querySelector(`link[rel="${rel}"]`);
        if (!el) {
            el = document.createElement('link');
            el.setAttribute('rel', rel);
            document.head.appendChild(el);
        }
        el.setAttribute('href', href);
    }

    function injectSchema(schemaObj) {
        const script = document.createElement('script');
        script.type = 'application/ld+json';
        script.textContent = JSON.stringify(schemaObj, null, 2);
        document.head.appendChild(script);
    }

    function removeExistingSchemas() {
        document.querySelectorAll('script[type="application/ld+json"]').forEach(s => s.remove());
    }

    // ── Schema: Organization ─────────────────────────────────────────────────
    function buildOrganizationSchema(org, siteUrl) {
        return {
            "@context": "https://schema.org",
            "@type": "Organization",
            "name": org.name,
            "url": org.url || siteUrl,
            "logo": {
                "@type": "ImageObject",
                "url": org.logo
            },
            "description": org.description,
            "telephone": org.telephone,
            "email": org.email,
            "sameAs": [
                org.facebook,
                org.instagram,
                org.linkedin
            ].filter(Boolean),
            "address": {
                "@type": "PostalAddress",
                "streetAddress": org.address_street,
                "addressLocality": org.address_city,
                "addressRegion": org.address_region,
                "addressCountry": org.address_country,
                "postalCode": org.address_postal
            }
        };
    }

    // ── Schema: LocalBusiness (MedicalBusiness) ──────────────────────────────
    function buildLocalBusinessSchema(org, extra) {
        const schema = {
            "@context": "https://schema.org",
            "@type": "MedicalBusiness",
            "name": org.name,
            "url": org.url,
            "logo": org.logo,
            "image": org.logo,
            "description": org.description,
            "telephone": [org.telephone, org.telephone2].filter(Boolean),
            "email": org.email,
            "priceRange": org.price_range || "$$",
            "openingHours": org.opening_hours,
            "address": {
                "@type": "PostalAddress",
                "streetAddress": org.address_street,
                "addressLocality": org.address_city,
                "addressRegion": org.address_region,
                "addressCountry": org.address_country,
                "postalCode": org.address_postal
            },
            "geo": {
                "@type": "GeoCoordinates",
                "latitude": parseFloat(org.latitude) || -33.4076,
                "longitude": parseFloat(org.longitude) || -70.5694
            },
            "sameAs": [org.facebook, org.instagram, org.linkedin].filter(Boolean)
        };
        if (extra && extra.medicalSpecialty) {
            schema.medicalSpecialty = extra.medicalSpecialty;
        }
        if (extra && extra.isAcceptingNewPatients !== undefined) {
            schema.isAcceptingNewPatients = extra.isAcceptingNewPatients;
        }
        return schema;
    }

    // ── Schema: Breadcrumb ────────────────────────────────────────────────────
    function buildBreadcrumbSchema(siteUrl, pageName, pageUrl) {
        if (PAGE_KEY === 'index') return null;
        return {
            "@context": "https://schema.org",
            "@type": "BreadcrumbList",
            "itemListElement": [
                {
                    "@type": "ListItem",
                    "position": 1,
                    "name": "Inicio",
                    "item": siteUrl + "/"
                },
                {
                    "@type": "ListItem",
                    "position": 2,
                    "name": pageName,
                    "item": pageUrl
                }
            ]
        };
    }

    // ── Schema: WebSite (sitelinks searchbox) ─────────────────────────────────
    function buildWebSiteSchema(siteUrl, name) {
        return {
            "@context": "https://schema.org",
            "@type": "WebSite",
            "name": name,
            "url": siteUrl
        };
    }

    // ── Schema: ContactPage ───────────────────────────────────────────────────
    function buildContactPageSchema(org, pageUrl) {
        return {
            "@context": "https://schema.org",
            "@type": "ContactPage",
            "name": "Contacto — " + org.name,
            "url": pageUrl,
            "mainEntity": {
                "@type": "Organization",
                "name": org.name,
                "telephone": org.telephone,
                "email": org.email,
                "address": {
                    "@type": "PostalAddress",
                    "streetAddress": org.address_street,
                    "addressLocality": org.address_city,
                    "addressRegion": org.address_region,
                    "addressCountry": org.address_country
                }
            }
        };
    }

    // ── Schema: AboutPage ─────────────────────────────────────────────────────
    function buildAboutPageSchema(org, pageUrl) {
        return {
            "@context": "https://schema.org",
            "@type": "AboutPage",
            "name": "Quiénes Somos — " + org.name,
            "url": pageUrl,
            "description": "Historia, valores y equipo fundador de CENEA, centro neurológico especializado en Las Condes, Santiago de Chile.",
            "publisher": {
                "@type": "Organization",
                "name": org.name,
                "url": org.url
            }
        };
    }

    // ── Schema: MedicalOrganization (equipo médico) ───────────────────────────
    function buildMedicalOrgSchema(org, pageUrl) {
        return {
            "@context": "https://schema.org",
            "@type": "MedicalOrganization",
            "name": org.name,
            "url": pageUrl,
            "description": "Equipo de más de 30 especialistas en neurología adultos, infantil, neuropediatría y rehabilitación.",
            "medicalSpecialty": [
                "Neurology",
                "Pediatrics",
                "Rehabilitation"
            ],
            "address": {
                "@type": "PostalAddress",
                "streetAddress": org.address_street,
                "addressLocality": org.address_city,
                "addressRegion": org.address_region,
                "addressCountry": org.address_country
            }
        };
    }

    // ── Schema: ItemList de Servicios ─────────────────────────────────────────
    function buildServicesSchema(org, pageUrl) {
        const servicios = [
            "Neurología General", "Neurología Infantil", "Epilepsia", "Cefaleas",
            "Trastornos del Movimiento", "Demencias", "Neurología Vascular",
            "Enfermedades Neuromusculares", "Enfermedades Desmielinizantes",
            "Trastornos del Desarrollo", "Trastornos del Sueño", "Neuroinfectología",
            "Parálisis Cerebral", "Neurocirugía", "Telemedicina"
        ];
        return {
            "@context": "https://schema.org",
            "@type": "ItemList",
            "name": "Especialidades Neurológicas — " + org.name,
            "url": pageUrl,
            "description": "16 especialidades neurológicas en CENEA Las Condes, Santiago.",
            "itemListElement": servicios.map((s, i) => ({
                "@type": "ListItem",
                "position": i + 1,
                "name": s,
                "url": pageUrl
            }))
        };
    }

    // ── Main ──────────────────────────────────────────────────────────────────
    fetch(jsonUrl)
        .then(r => { if (!r.ok) throw new Error('seo.json no encontrado'); return r.json(); })
        .then(seo => {
            const global = seo.global || {};
            const page   = (seo.pages || {})[PAGE_KEY] || {};
            const org    = global.schema_organization || {};
            const siteUrl = (org.url || global.site_url || 'https://cenea.cl').replace(/\/$/, '');

            // ── <title> ──────────────────────────────────────────────────────
            if (page.title) document.title = page.title;

            // ── theme-color ──────────────────────────────────────────────────
            setMeta('theme-color', global.theme_color);

            // ── Meta básicas ─────────────────────────────────────────────────
            setMeta('description', page.description);
            setMeta('robots', page.robots || global.robots_global || 'index, follow');
            setMeta('author', global.author);

            // ── Canonical ────────────────────────────────────────────────────
            if (page.canonical) setLink('canonical', page.canonical);

            // ── Open Graph ───────────────────────────────────────────────────
            setMeta('og:type',        page.og_type || 'website',        'property');
            setMeta('og:site_name',   global.site_name,                 'property');
            setMeta('og:title',       page.og_title || page.title,      'property');
            setMeta('og:description', page.og_description || page.description, 'property');
            setMeta('og:url',         page.canonical,                   'property');
            setMeta('og:image',       page.og_image || global.og_image, 'property');
            setMeta('og:image:width', global.og_image_width || '1200',  'property');
            setMeta('og:image:height',global.og_image_height || '630',  'property');
            setMeta('og:locale',      global.locale || 'es_CL',         'property');

            // ── Twitter Cards ─────────────────────────────────────────────────
            setMeta('twitter:card',        page.twitter_card || 'summary_large_image');
            setMeta('twitter:site',        global.twitter_site);
            setMeta('twitter:title',       page.twitter_title || page.og_title || page.title);
            setMeta('twitter:description', page.twitter_description || page.og_description || page.description);
            setMeta('twitter:image',       page.og_image || global.og_image);

            // ── JSON-LD Schemas ───────────────────────────────────────────────
            removeExistingSchemas();

            // Siempre: Organization + WebSite en home, solo Organization en el resto
            if (PAGE_KEY === 'index') {
                injectSchema(buildWebSiteSchema(siteUrl, org.name));
                injectSchema(buildLocalBusinessSchema(org, page.schema_extra));
            } else {
                injectSchema(buildOrganizationSchema(org, siteUrl));
            }

            // Breadcrumb en páginas internas
            const breadcrumb = buildBreadcrumbSchema(siteUrl, page.og_title || page.title, page.canonical);
            if (breadcrumb) injectSchema(breadcrumb);

            // Schema específico por página
            switch (page.schema_type) {
                case 'ContactPage':
                    injectSchema(buildContactPageSchema(org, page.canonical));
                    break;
                case 'AboutPage':
                    injectSchema(buildAboutPageSchema(org, page.canonical));
                    break;
                case 'MedicalOrganization':
                    injectSchema(buildMedicalOrgSchema(org, page.canonical));
                    break;
                case 'MedicalBusiness':
                    injectSchema(buildServicesSchema(org, page.canonical));
                    break;
                default:
                    break;
            }
        })
        .catch(err => {
            console.warn('[seo-injector] No se pudo cargar seo.json:', err.message);
        });
})();
