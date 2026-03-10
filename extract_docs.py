import json
from bs4 import BeautifulSoup

html_file = "equipo-medico.html"
with open(html_file, "r", encoding="utf-8") as f:
    soup = BeautifulSoup(f.read(), "html.parser")

profiles = []
for idx, card in enumerate(soup.select('.doc-card')):
    category = card.get('data-category', '')
    name_el = card.select_one('.doc-card__name')
    name = name_el.get_text(strip=True) if name_el else ''
    
    role_el = card.select_one('.doc-card__role')
    role = role_el.get_text(strip=True) if role_el else ''
    
    img_el = card.select_one('img')
    img_src = img_el.get('src', '') if img_el else ''
    
    bio_el = card.select_one('.doc-card__bio')
    bio_html = bio_el.decode_contents().strip() if bio_el else ''
    
    # We will just generate a unique id base on name
    profile_id = str(idx + 1)
    
    profiles.append({
        "id": profile_id,
        "nombre": name,
        "especialidad": role,
        "bio": bio_html,
        "categoria": category,
        "link_online": "https://www.medilink.com/", # placeholder or empty
        "link_presencial": "https://www.medilink.com/", # placeholder or empty
        "imagen": img_src
    })

with open("data/perfiles.json", "w", encoding="utf-8") as f:
    json.dump(profiles, f, indent=4, ensure_ascii=False)

print(f"Extracted {len(profiles)} profiles.")
