"""
Python server that replicates the PHP admin backend for Cenea.
Run: python3 serve.py
Then open: http://localhost:8000/admin/index.php
"""
from http.server import HTTPServer, SimpleHTTPRequestHandler
from urllib.parse import parse_qs, urlparse, unquote
import os
import json
import uuid
import html
import email.parser
import time

ADMIN_USER = "admin"
ADMIN_PASS = "admin"
BASE_DIR = "/Users/mac01/backcenea"
CONFIG_FILE = os.path.join(BASE_DIR, "data", "site_content.json")

sessions = {}


def load_config():
    with open(CONFIG_FILE, "r", encoding="utf-8") as f:
        return json.load(f)


def save_config(config):
    with open(CONFIG_FILE, "w", encoding="utf-8") as f:
        json.dump(config, f, indent=4, ensure_ascii=False)


class PHPHandler(SimpleHTTPRequestHandler):

    def do_GET(self):
        parsed = urlparse(self.path)
        path = parsed.path

        if path == "/admin/index.php" or path == "/admin/index.php/":
            self.serve_login()
        elif path == "/admin/dashboard.php":
            if self.is_logged_in():
                self.serve_dashboard()
            else:
                self.redirect("/admin/index.php")
        elif path == "/admin/logout.php":
            cookie = self.get_session_id()
            if cookie in sessions:
                del sessions[cookie]
            self.send_response(302)
            self.send_header("Location", "/admin/index.php")
            self.send_header("Set-Cookie", "session_id=; Max-Age=0")
            self.end_headers()
        else:
            super().do_GET()

    def end_headers(self):
        # Prevent caching on JSON and JS files so frontend always gets fresh data
        if hasattr(self, 'path') and (self.path.endswith('.json') or self.path.endswith('.js')):
            self.send_header("Cache-Control", "no-store, no-cache, must-revalidate, max-age=0")
            self.send_header("Pragma", "no-cache")
            self.send_header("Expires", "0")
        super().end_headers()

    def do_POST(self):
        parsed = urlparse(self.path)
        path = parsed.path

        if path == "/admin/index.php":
            self.handle_login_post()
        elif path == "/admin/save_content.php":
            if not self.is_logged_in():
                self.redirect("/admin/index.php")
                return
            self.handle_save_content()
        elif path == "/admin/upload.php":
            if not self.is_logged_in():
                self.redirect("/admin/index.php")
                return
            self.handle_upload()
        else:
            self.send_error(405)

    def redirect(self, url):
        self.send_response(302)
        self.send_header("Location", url)
        self.end_headers()

    def get_session_id(self):
        cookies = self.headers.get("Cookie", "")
        for part in cookies.split(";"):
            part = part.strip()
            if part.startswith("session_id="):
                return part.split("=", 1)[1]
        return ""

    def is_logged_in(self):
        sid = self.get_session_id()
        return sid in sessions and sessions[sid].get("logged_in")

    def parse_multipart(self):
        content_type = self.headers.get("Content-Type", "")
        length = int(self.headers.get("Content-Length", 0))
        fields = {}
        files = {}

        if "multipart/form-data" in content_type:
            body = self.rfile.read(length)
            # Extract boundary from content type
            for part in content_type.split(";"):
                part = part.strip()
                if part.startswith("boundary="):
                    boundary = part.split("=", 1)[1].strip('"')
                    break
            else:
                return fields, files

            boundary_bytes = boundary.encode()
            delimiter = b"--" + boundary_bytes
            parts = body.split(delimiter)

            for part in parts[1:]:  # skip preamble
                if part.startswith(b"--"):
                    break  # end boundary
                if b"\r\n\r\n" not in part:
                    continue
                header_data, value = part.split(b"\r\n\r\n", 1)
                # Remove trailing \r\n
                if value.endswith(b"\r\n"):
                    value = value[:-2]

                headers_str = header_data.decode("utf-8", errors="replace")
                # Parse Content-Disposition
                name = None
                filename = None
                for line in headers_str.split("\r\n"):
                    if line.lower().startswith("content-disposition:"):
                        for param in line.split(";"):
                            param = param.strip()
                            if param.startswith("name="):
                                name = param.split("=", 1)[1].strip('"')
                            elif param.startswith("filename="):
                                filename = param.split("=", 1)[1].strip('"')

                if name is None:
                    continue

                if filename is not None:
                    # This is a file input - only store if a file was actually selected
                    if filename:
                        files[name] = {"filename": filename, "data": value}
                    # If filename is empty string, user didn't select a file - skip it
                else:
                    fields[name] = value.decode("utf-8", errors="replace")
        else:
            body = self.rfile.read(length).decode()
            params = parse_qs(body, keep_blank_values=True)
            for k, v in params.items():
                fields[k] = v[0]

        return fields, files

    def handle_login_post(self):
        length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(length).decode()
        params = parse_qs(body)
        username = params.get("username", [""])[0]
        password = params.get("password", [""])[0]

        if username == ADMIN_USER and password == ADMIN_PASS:
            sid = str(uuid.uuid4())
            sessions[sid] = {"logged_in": True}
            self.send_response(302)
            self.send_header("Location", "/admin/dashboard.php")
            self.send_header("Set-Cookie", f"session_id={sid}; Path=/")
            self.end_headers()
        else:
            self.serve_login(error="Credenciales incorrectas")

    def handle_save_content(self):
        fields, files = self.parse_multipart()
        page = fields.get("page", "")
        valid_pages = ["home", "quienes_somos", "servicios", "contacto"]

        if page not in valid_pages:
            self.redirect("/admin/dashboard.php?error=Pagina+no+valida")
            return

        config = load_config()
        if page not in config:
            config[page] = {}

        for key, value in fields.items():
            if key != "page":
                config[page][key] = value

        allowed_ext = ["jpg", "jpeg", "png", "webp", "gif"]
        for key, fdata in files.items():
            ext = fdata["filename"].rsplit(".", 1)[-1].lower() if "." in fdata["filename"] else ""
            if ext in allowed_ext:
                upload_dir = os.path.join(BASE_DIR, "img", "uploads")
                os.makedirs(upload_dir, exist_ok=True)
                filename = f"{page}_{key}_{int(time.time())}.{ext}"
                filepath = os.path.join(upload_dir, filename)
                with open(filepath, "wb") as f:
                    f.write(fdata["data"])
                config[page][key] = f"img/uploads/{filename}"

        save_config(config)
        self.redirect(f"/admin/dashboard.php?success=Contenido+de+{page}+actualizado+correctamente")

    def handle_upload(self):
        fields, files = self.parse_multipart()
        key = fields.get("image_key", "")
        allowed_keys = ["about_img_1", "about_img_2"]

        if key not in allowed_keys:
            self.redirect("/admin/dashboard.php?error=Invalid+image+key")
            return

        if "image_file" not in files:
            self.redirect("/admin/dashboard.php?error=No+file+uploaded")
            return

        fdata = files["image_file"]
        ext = fdata["filename"].rsplit(".", 1)[-1].lower() if "." in fdata["filename"] else ""
        allowed_ext = ["jpg", "jpeg", "png", "webp"]

        if ext not in allowed_ext:
            self.redirect("/admin/dashboard.php?error=Invalid+file+type")
            return

        upload_dir = os.path.join(BASE_DIR, "imghome", "uploads")
        os.makedirs(upload_dir, exist_ok=True)
        filename = f"{key}_{int(time.time())}.{ext}"
        filepath = os.path.join(upload_dir, filename)
        with open(filepath, "wb") as f:
            f.write(fdata["data"])

        config = load_config()
        config[key] = f"imghome/uploads/{filename}"
        save_config(config)
        self.redirect("/admin/dashboard.php?success=Image+updated+successfully!")

    # ── HTML Pages ──────────────────────────────────────────────

    def send_html(self, content, code=200):
        self.send_response(code)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.end_headers()
        self.wfile.write(content.encode())

    def serve_login(self, error=""):
        error_html = ""
        if error:
            error_html = f'<p class="error-msg">{html.escape(error)}</p>'

        page = f"""<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Cenea</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        body {{ display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f4f4; margin: 0; font-family: 'Inter', sans-serif; }}
        .login-card {{ background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 100%; max-width: 400px; text-align: center; }}
        .login-card h1 {{ margin-bottom: 1.5rem; color: #333; }}
        .form-group {{ margin-bottom: 1rem; text-align: left; }}
        .form-group label {{ display: block; margin-bottom: 0.5rem; color: #666; }}
        .form-group input {{ width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }}
        .btn-login {{ width: 100%; padding: 0.75rem; background-color: #004d40; color: white; border: none; border-radius: 4px; font-size: 1rem; cursor: pointer; transition: background 0.3s; }}
        .btn-login:hover {{ background-color: #00332a; }}
        .error-msg {{ color: red; margin-bottom: 1rem; font-size: 0.9rem; }}
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Admin Cenea</h1>
        {error_html}
        <form method="POST">
            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username" required autofocus>
            </div>
            <div class="form-group">
                <label for="password">Contrasena</label>
                <input type="password" id="password" name="password" required>
            </div>
            <button type="submit" class="btn-login">Ingresar</button>
        </form>
        <p style="margin-top: 1rem;"><a href="../index.html">&larr; Volver al sitio</a></p>
    </div>
</body>
</html>"""
        self.send_html(page)

    def serve_dashboard(self):
        parsed = urlparse(self.path)
        params = parse_qs(parsed.query)
        msg = params.get("success", [""])[0]
        err = params.get("error", [""])[0]

        config = load_config()

        def val(section, key):
            return html.escape(config.get(section, {}).get(key, ""))

        def img_preview(section, key):
            path = config.get(section, {}).get(key, "")
            if path:
                return f'<img src="../{html.escape(path)}" class="current-img-preview">'
            return ""

        alert_html = ""
        if msg:
            alert_html += f'<div class="alert alert-success">{html.escape(unquote(msg))}</div>'
        if err:
            alert_html += f'<div class="alert alert-error">{html.escape(unquote(err))}</div>'

        page = f"""<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - Cenea</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body {{ font-family: 'Inter', sans-serif; background: #f9fafb; margin: 0; padding: 20px; }}
        header {{ display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }}
        h1 {{ margin: 0; color: #111; }}
        .logout {{ color: #d32f2f; text-decoration: none; font-weight: 500; }}
        .container {{ max-width: 1000px; margin: 0 auto; }}
        .alert {{ padding: 10px; border-radius: 4px; margin-bottom: 20px; }}
        .alert-success {{ background: #d4edda; color: #155724; }}
        .alert-error {{ background: #f8d7da; color: #721c24; }}
        .tabs {{ display: flex; border-bottom: 2px solid #ddd; margin-bottom: 20px; }}
        .tab-btn {{ padding: 10px 20px; cursor: pointer; background: none; border: none; font-size: 1rem; font-weight: 500; color: #666; margin-right: 5px; border-radius: 4px 4px 0 0; }}
        .tab-btn.active {{ background: #fff; border: 2px solid #ddd; border-bottom: 2px solid #fff; margin-bottom: -2px; color: #004d40; }}
        .tab-content {{ display: none; background: white; padding: 20px; border-radius: 0 4px 4px 4px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #ddd; border-top: none; }}
        .tab-content.active {{ display: block; }}
        .form-group {{ margin-bottom: 1.5rem; }}
        label {{ display: block; margin-bottom: 0.5rem; font-weight: 600; color: #333; }}
        input[type="text"], textarea {{ width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; font-size: 1rem; box-sizing: border-box; }}
        textarea {{ resize: vertical; min-height: 100px; }}
        .current-img-preview {{ max-width: 200px; border-radius: 4px; border: 1px solid #eee; margin: 10px 0; display: block; }}
        button.btn-save {{ background: #004d40; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 1rem; margin-top: 1rem; }}
        button.btn-save:hover {{ background: #00332a; }}
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>Administrar Contenido</h1>
            <a href="logout.php" class="logout">Cerrar Sesion</a>
        </header>

        {alert_html}

        <div class="tabs">
            <button class="tab-btn active" onclick="openTab(event, 'home')">Inicio</button>
            <button class="tab-btn" onclick="openTab(event, 'quienes_somos')">Quienes Somos</button>
            <button class="tab-btn" onclick="openTab(event, 'servicios')">Servicios</button>
            <button class="tab-btn" onclick="openTab(event, 'contacto')">Contacto</button>
        </div>

        <!-- HOME TAB -->
        <div id="home" class="tab-content active">
            <form action="save_content.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="page" value="home">
                <h3>Hero Section</h3>
                <div class="form-group">
                    <label>Titulo Principal</label>
                    <input type="text" name="hero_title" value="{val('home','hero_title')}">
                </div>
                <div class="form-group">
                    <label>Subtitulo</label>
                    <textarea name="hero_subtitle">{val('home','hero_subtitle')}</textarea>
                </div>
                <h3>Seccion "Quienes Somos" (Inicio)</h3>
                <div class="form-group">
                    <label>Titulo</label>
                    <input type="text" name="about_title" value="{val('home','about_title')}">
                </div>
                <div class="form-group">
                    <label>Descripcion 1</label>
                    <textarea name="about_desc_1">{val('home','about_desc_1')}</textarea>
                </div>
                <div class="form-group">
                    <label>Descripcion 2</label>
                    <textarea name="about_desc_2">{val('home','about_desc_2')}</textarea>
                </div>
                <div class="form-group">
                    <label>Imagen 1</label>
                    {img_preview('home','about_img_1')}
                    <input type="file" name="about_img_1">
                </div>
                <div class="form-group">
                    <label>Imagen 2</label>
                    {img_preview('home','about_img_2')}
                    <input type="file" name="about_img_2">
                </div>
                <button type="submit" class="btn-save">Guardar Cambios Inicio</button>
            </form>
        </div>

        <!-- QUIENES SOMOS TAB -->
        <div id="quienes_somos" class="tab-content">
            <form action="save_content.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="page" value="quienes_somos">
                <h3>Cabecera</h3>
                <div class="form-group">
                    <label>Titulo Cabecera</label>
                    <input type="text" name="header_title" value="{val('quienes_somos','header_title')}">
                </div>
                <div class="form-group">
                    <label>Bajada Cabecera</label>
                    <textarea name="header_subtitle">{val('quienes_somos','header_subtitle')}</textarea>
                </div>
                <h3>Introduccion</h3>
                <div class="form-group">
                    <label>Titulo Intro</label>
                    <input type="text" name="intro_title" value="{val('quienes_somos','intro_title')}">
                </div>
                <div class="form-group">
                    <label>Descripcion 1</label>
                    <textarea name="intro_desc_1">{val('quienes_somos','intro_desc_1')}</textarea>
                </div>
                <div class="form-group">
                    <label>Descripcion 2</label>
                    <textarea name="intro_desc_2">{val('quienes_somos','intro_desc_2')}</textarea>
                </div>
                <div class="form-group">
                    <label>Imagen Intro</label>
                    {img_preview('quienes_somos','intro_img')}
                    <input type="file" name="intro_img">
                </div>
                <h3>Socios Fundadores</h3>
                <div class="form-group">
                    <label>Titulo Fundadores</label>
                    <input type="text" name="fundadores_title" value="{val('quienes_somos','fundadores_title')}">
                </div>
                <div class="form-group">
                    <label>Descripcion 1</label>
                    <textarea name="fundadores_desc_1">{val('quienes_somos','fundadores_desc_1')}</textarea>
                </div>
                <div class="form-group">
                    <label>Descripcion 2</label>
                    <textarea name="fundadores_desc_2">{val('quienes_somos','fundadores_desc_2')}</textarea>
                </div>
                <div class="form-group">
                    <label>Imagen Fundadores</label>
                    {img_preview('quienes_somos','fundadores_img')}
                    <input type="file" name="fundadores_img">
                </div>
                <button type="submit" class="btn-save">Guardar Cambios Quienes Somos</button>
            </form>
        </div>

        <!-- SERVICIOS TAB -->
        <div id="servicios" class="tab-content">
            <form action="save_content.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="page" value="servicios">
                <h3>Cabecera</h3>
                <div class="form-group">
                    <label>Titulo</label>
                    <input type="text" name="header_title" value="{val('servicios','header_title')}">
                </div>
                <div class="form-group">
                    <label>Bajada</label>
                    <textarea name="header_subtitle">{val('servicios','header_subtitle')}</textarea>
                </div>
                <button type="submit" class="btn-save">Guardar Cambios Servicios</button>
            </form>
        </div>

        <!-- CONTACTO TAB -->
        <div id="contacto" class="tab-content">
            <form action="save_content.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="page" value="contacto">
                <h3>Cabecera</h3>
                <div class="form-group">
                    <label>Titulo</label>
                    <input type="text" name="header_title" value="{val('contacto','header_title')}">
                </div>
                <div class="form-group">
                    <label>Bajada</label>
                    <textarea name="header_subtitle">{val('contacto','header_subtitle')}</textarea>
                </div>
                <h3>Informacion</h3>
                <div class="form-group">
                    <label>Titulo Info</label>
                    <input type="text" name="info_title" value="{val('contacto','info_title')}">
                </div>
                <div class="form-group">
                    <label>Descripcion Info</label>
                    <textarea name="info_desc">{val('contacto','info_desc')}</textarea>
                </div>
                <button type="submit" class="btn-save">Guardar Cambios Contacto</button>
            </form>
        </div>

        <p style="margin-top: 2rem;"><a href="../index.html" target="_blank">Ver sitio web &rarr;</a></p>
    </div>

    <script>
        function openTab(evt, tabName) {{
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName("tab-content");
            for (i = 0; i < tabcontent.length; i++) {{
                tabcontent[i].style.display = "none";
                tabcontent[i].classList.remove("active");
            }}
            tablinks = document.getElementsByClassName("tab-btn");
            for (i = 0; i < tablinks.length; i++) {{
                tablinks[i].classList.remove("active");
            }}
            document.getElementById(tabName).style.display = "block";
            evt.currentTarget.classList.add("active");
        }}
    </script>
</body>
</html>"""
        self.send_html(page)


os.chdir(BASE_DIR)
server = HTTPServer(("localhost", 8000), PHPHandler)
print("Servidor corriendo en http://localhost:8000")
print("Login en: http://localhost:8000/admin/index.php")
print("Credenciales: admin / admin")
print("Presiona Ctrl+C para detener")
server.serve_forever()
