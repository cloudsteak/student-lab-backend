# --- azure_lab_backend/utils.py ---

import random
import string

def generate_credentials():
    username = "tanulo-" + ''.join(random.choices(string.ascii_lowercase + string.digits, k=6))
    password = ''.join(random.choices(string.ascii_letters + string.digits + "!@#$%^&*()", k=12))
    return username, password
