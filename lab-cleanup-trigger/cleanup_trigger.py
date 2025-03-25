# cleanup_trigger.py

import os
import httpx
import logging

AUTH0_DOMAIN = os.getenv("AUTH0_DOMAIN")
AUTH0_CLIENT_ID = os.getenv("AUTH0_CLIENT_ID")
AUTH0_CLIENT_SECRET = os.getenv("AUTH0_CLIENT_SECRET")
AUTH0_AUDIENCE = os.getenv("AUTH0_AUDIENCE")

BACKEND_URL = os.getenv("BACKEND_URL")
LAB_STATUS_ENDPOINT = f"{BACKEND_URL}/lab-status/all"
CLEANUP_ENDPOINT = f"{BACKEND_URL}/clean-up"

logging.basicConfig(level=logging.INFO)


def get_auth_token():
    url = f"https://{AUTH0_DOMAIN}/oauth/token"
    payload = {
        "grant_type": "client_credentials",
        "client_id": AUTH0_CLIENT_ID,
        "client_secret": AUTH0_CLIENT_SECRET,
        "audience": AUTH0_AUDIENCE
    }
    response = httpx.post(url, json=payload)
    response.raise_for_status()
    return response.json()["access_token"]


def cleanup_expired_labs():
    token = get_auth_token()
    headers = {"Authorization": f"Bearer {token}"}

    response = httpx.get(LAB_STATUS_ENDPOINT, headers=headers)
    response.raise_for_status()
    labs = response.json()

    for lab in labs:
        if lab["status"] == "expired":
            logging.info(f"Triggering clean-up for {lab['username']}")
            res = httpx.post(CLEANUP_ENDPOINT, headers=headers, json={"username": lab["username"]})
            if res.status_code == 200:
                logging.info(f"Cleaned up lab {lab['username']}")
            else:
                logging.warning(f"Failed to clean up lab {lab['username']}: {res.status_code} {res.text}")


if __name__ == "__main__":
    cleanup_expired_labs()
