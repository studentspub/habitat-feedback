#!/usr/bin/env python3
"""
Habitat-Feedback Geocode-Worker.

Holt offene Adressen aus /api/pi/queue, geokodiert über Nominatim,
bestimmt den Straßenabschnitt (Zehnerblock-Fallback, siehe Bauplan
Abschnitt 03/04) und schreibt das Ergebnis über /api/pi/resolve
zurück. Läuft während der Entwicklung manuell/lokal gegen die
WSL-Distro, später unverändert als systemd-Timer auf dem Raspberry
Pi gegen die echte Strato-URL (siehe worker/config.py).
"""
import math
import sys
import time

import requests

from config import BASE_URL, PI_API_TOKEN

# Nominatims Nutzungsbedingungen verlangen einen User-Agent, der die App und
# eine Kontaktmöglichkeit nennt. Vor dem Live-Betrieb hier eine echte Domain/
# E-Mail eintragen — ein Platzhalter wie "example.invalid" wird von Nominatims
# Filter aktiv geblockt (403), das ist keine Kleinigkeit, siehe hierzu auch
# https://operations.osmfoundation.org/policies/nominatim/
HEADERS = {"User-Agent": "habitat-feedback-worker/1.0 (+https://habitat-feedback.de; cihanaldanmaz@gmail.com)"}
AUTH_HEADERS = {**HEADERS, "Authorization": f"Bearer {PI_API_TOKEN}"}
NOMINATIM_DELAY_SECONDS = 1.1
OVERPASS_DELAY_SECONDS = 1.1


def fetch_queue() -> list[dict]:
    r = requests.get(f"{BASE_URL}/api/pi/queue", headers=AUTH_HEADERS, timeout=15)
    r.raise_for_status()
    return r.json()["items"]


def geocode(raw_address: str) -> dict | None:
    r = requests.get(
        "https://nominatim.openstreetmap.org/search",
        params={"q": raw_address, "format": "jsonv2", "addressdetails": 1, "limit": 1},
        headers=HEADERS,
        timeout=15,
    )
    time.sleep(NOMINATIM_DELAY_SECONDS)
    r.raise_for_status()
    results = r.json()
    return results[0] if results else None


def segment_index_for_housenumber(housenumber: str) -> int:
    """Zehnerblock-Fallback: '3' -> Block 1 (1-10), '12' -> Block 2 (11-20)."""
    digits = "".join(ch for ch in housenumber if ch.isdigit())
    if not digits:
        return 1
    n = int(digits)
    return max(1, math.ceil(n / 10))


OVERPASS_RADIUS_METERS = 250


def count_addresses_overpass(street: str, lat: float, lon: float) -> int:
    """Zählt Adresspunkte derselben Straße im Umkreis um den Geokodierungspunkt.

    Ein `around:`-Radius statt eines reinen Straßen-/PLZ-Filters — sonst
    durchsucht Overpass die komplette Datenbank für das Tag-Paar und läuft
    auf der öffentlichen Instanz zuverlässig in einen 504 Gateway Timeout.
    Bewusst grob (zählt die Umgebung, nicht exakt den Zehnerblock) — das
    tendiert eher zu einem höheren Wert, nie zu einem niedrigeren, also in
    die für die Sichtbarkeitsschwelle unkritische Richtung.
    TODO Phase 3: echte Blockgrenzen aus OSM-Way-Segmenten ableiten.
    """
    query = f"""
    [out:json][timeout:25];
    node(around:{OVERPASS_RADIUS_METERS},{lat},{lon})["addr:housenumber"]["addr:street"="{street}"];
    out count;
    """
    r = requests.post("https://overpass-api.de/api/interpreter", data={"data": query}, headers=HEADERS, timeout=30)
    time.sleep(OVERPASS_DELAY_SECONDS)
    r.raise_for_status()
    data = r.json()
    elements = data.get("elements", [])
    if not elements:
        return 0
    return int(elements[0].get("tags", {}).get("total", 0))


def resolve_and_push(item: dict, geo: dict) -> None:
    address = geo.get("address", {})
    street = address.get("road") or address.get("pedestrian") or address.get("footway")
    if not street:
        print(f"  [skip] keine Straße in Geocoding-Ergebnis für Queue-Eintrag {item['id']}")
        return

    housenumber = address.get("house_number", "0")
    postcode = address.get("postcode", "00000")
    city = address.get("city") or address.get("town") or address.get("village") or "Unbekannt"
    segment_index = segment_index_for_housenumber(housenumber)
    address_count = count_addresses_overpass(street, float(geo["lat"]), float(geo["lon"]))

    body = {
        "queue_id": item["id"],
        "street_name": street,
        "segment_index": segment_index,
        "postal_code": postcode,
        "city": city,
        "centroid_lat": float(geo["lat"]),
        "centroid_lon": float(geo["lon"]),
        "address_count": address_count,
        "scores": item["payload"]["scores"],
        "fingerprint_raw": item["payload"]["fingerprint_raw"],
    }
    r = requests.post(f"{BASE_URL}/api/pi/resolve", json=body, headers=AUTH_HEADERS, timeout=15)
    if r.status_code == 409:
        print(f"  [dupe] Queue-Eintrag {item['id']}: Gerät hat diesen Abschnitt bereits bewertet")
        return
    r.raise_for_status()
    print(f"  [ok] Queue-Eintrag {item['id']} -> Segment {r.json().get('segment_id')} ({street}, Block {segment_index})")


def run() -> None:
    items = fetch_queue()
    print(f"{len(items)} offene Adresse(n) in der Warteschlange.")
    for item in items:
        try:
            geo = geocode(item["raw_address"])
            if geo is None:
                print(f"  [skip] Nominatim fand nichts für '{item['raw_address']}'")
                continue
            resolve_and_push(item, geo)
        except requests.RequestException as e:
            # Ein einzelner fehlgeschlagener Eintrag (z.B. Overpass 429/504)
            # bricht nicht den ganzen Lauf ab — bleibt einfach in der
            # Warteschlange und wird beim nächsten Durchlauf erneut versucht.
            print(f"  [retry später] Queue-Eintrag {item['id']}: {e}")


if __name__ == "__main__":
    try:
        run()
    except requests.RequestException as e:
        print(f"Fehler: {e}", file=sys.stderr)
        sys.exit(1)
