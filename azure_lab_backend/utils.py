# --- azure_lab_backend/utils.py ---

import random
import string
import os

import requests
from jose import jwt
from jose.exceptions import JWTError

def get_auth0_jwks():
    domain = os.getenv("AUTH0_DOMAIN")
    url = f"https://{domain}/.well-known/jwks.json"
    return requests.get(url).json()

def get_rsa_key(token):
    unverified_header = jwt.get_unverified_header(token)
    jwks = get_auth0_jwks()

    for key in jwks["keys"]:
        if key["kid"] == unverified_header["kid"]:
            return {
                "kty": key["kty"],
                "kid": key["kid"],
                "use": key["use"],
                "n": key["n"],
                "e": key["e"]
            }

    raise JWTError("Unable to find appropriate key")


def generate_credentials():
    username = "tanulo-" + ''.join(random.choices(string.ascii_lowercase + string.digits, k=6))
    password = ''.join(random.choices(string.ascii_letters + string.digits + "!@#$%^&*()", k=18))
    return username, password
