# --- lab-backend/utils.py ---

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
    # Username: lowercase letters + digits
    username = "tanulo-" + ''.join(random.choices(string.ascii_lowercase + string.digits, k=6))

    # AWS-friendly character sets
    uppercase = string.ascii_uppercase
    lowercase = string.ascii_lowercase
    digits = string.digits
    symbols = "!@#$%^&*()_+-=[]{}|"  # AWS- and Azure-supported special characters

    # Ensure all requirements are met
    password_chars = [
        random.choice(uppercase),
        random.choice(lowercase),
        random.choice(digits),
        random.choice(symbols)
    ]

    # Fill remaining characters
    remaining_length = 18 - len(password_chars)
    all_chars = uppercase + lowercase + digits + symbols
    password_chars += random.choices(all_chars, k=remaining_length)

    # Shuffle the password to avoid predictable patterns
    random.shuffle(password_chars)
    password = ''.join(password_chars)

    return username, password

