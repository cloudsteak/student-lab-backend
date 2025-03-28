# cleanup_trigger.py

import os
import httpx
import logging
from datetime import datetime, timedelta, timezone

AUTH0_DOMAIN = os.getenv("AUTH0_DOMAIN")
AUTH0_CLIENT_ID = os.getenv("AUTH0_CLIENT_ID")
AUTH0_CLIENT_SECRET = os.getenv("AUTH0_CLIENT_SECRET")
AUTH0_AUDIENCE = os.getenv("AUTH0_AUDIENCE")

BACKEND_URL = os.getenv("BACKEND_URL")
LAB_STATUS_ENDPOINT = f"{BACKEND_URL}/lab-status/all"
CLEANUP_ENDPOINT = f"{BACKEND_URL}/clean-up-lab"
DELETE_REDIS_ENDPOINT = f"{BACKEND_URL}/lab-delete-internal"



logging.basicConfig(level=logging.INFO)
TIMEOUT = 10  # seconds

def get_auth_token():
    url = f"https://{AUTH0_DOMAIN}/oauth/token"
    payload = {
        "grant_type": "client_credentials",
        "client_id": AUTH0_CLIENT_ID,
        "client_secret": AUTH0_CLIENT_SECRET,
        "audience": AUTH0_AUDIENCE
    }
    response = httpx.post(url, json=payload, timeout=TIMEOUT)
    response.raise_for_status()
    return response.json()["access_token"]

def is_expired(lab):
    started_at_str = lab.get("started_at")
    ttl_seconds = lab.get("ttl_seconds", 3600)
    status = lab.get("status", "ready")

    if not started_at_str:
        return False

    try:
        started_at = datetime.fromisoformat(started_at_str).replace(tzinfo=timezone.utc)
    except Exception as e:
        logging.error(f"Invalid started_at for {lab.get('username')}: {started_at_str} - {e}")
        return False

    now = datetime.now(timezone.utc)

    if status == "ready":
        expiry_time = started_at + timedelta(seconds=ttl_seconds)
    else:
        expiry_time = started_at + timedelta(seconds=ttl_seconds * 2)

    return now >= expiry_time

def cleanup_expired_labs():
    token = get_auth_token()
    headers = {"Authorization": f"Bearer {token}"}

    response = httpx.get(LAB_STATUS_ENDPOINT, headers=headers, timeout=TIMEOUT)
    response.raise_for_status()
    labs_data = response.json()
    labs = labs_data.get("labs", [])
    
    if not isinstance(labs, list):
        logging.error("Invalid response format: %s", labs_data)
        return

    for lab in labs:
        username = lab.get("username")
        if is_expired(lab):
            logging.info(f"[EXPIRED] Cleaning up lab {username} (status: {lab.get('status')})")
            res = httpx.post(CLEANUP_ENDPOINT, headers=headers, json={"username": username}, timeout=TIMEOUT)
            if res.status_code == 200:
                logging.info(f"✔️ Lab {username} cleaned up")

                # Delete Redis record after cleanup
                del_res = httpx.post(DELETE_REDIS_ENDPOINT, headers=headers, json={"username": username}, timeout=TIMEOUT)
                if del_res.status_code == 200:
                    logging.info(f"🗑️ Deleted Redis key for {username}")
                else:
                    logging.warning(f"⚠️ Failed to delete Redis key for {username}: {del_res.status_code} {del_res.text}")
            else:
                logging.warning(f"⚠️ Failed to clean up lab {username}: {res.status_code} {res.text}")
        else:
            logging.debug(f"[ACTIVE] Skipping lab {username}, still within TTL")

if __name__ == "__main__":
    cleanup_expired_labs()
