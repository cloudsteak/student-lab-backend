# --- azure_lab_backend/main.py ---

from fastapi import FastAPI, Depends, HTTPException, status
from .utils import generate_credentials
from .emailer import send_lab_ready_email
from .models import LabRequest, LabStatus
from redis import Redis
import os
import json
from jose import jwt, JWTError
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials

app = FastAPI(docs_url="/docs", redoc_url=None)
security = HTTPBearer()

redis_client = Redis(
    host=os.getenv("REDIS_HOST", "localhost"),
    port=int(os.getenv("REDIS_PORT", 6379)),
    db=int(os.getenv("REDIS_DB", 0))
)

TTL = int(os.getenv("LAB_TTL_SECONDS", 3600))

# --- Auth0 validation ---
def verify_token(credentials: HTTPAuthorizationCredentials = Depends(security)):
    token = credentials.credentials
    try:
        payload = jwt.decode(
            token,
            key={},  # Public key URL validation step skipped for brevity
            audience=os.getenv("AUTH0_AUDIENCE"),
            issuer=f"https://{os.getenv('AUTH0_DOMAIN')}/",
            algorithms=[os.getenv("AUTH0_ALGORITHMS", "RS256")]
        )
        return payload
    except JWTError:
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token")

@app.get("/")
def root():
    return {"message": "Student Lab Backend API is up and running"}

@app.post("/start-lab")
def start_lab(request: LabRequest, token: dict = Depends(verify_token)):
    username, password = generate_credentials()
    lab_data = {
        "lab_name": request.lab_name,
        "username": username,
        "password": password
    }
    redis_client.setex(f"lab:{username}", TTL, json.dumps(lab_data))
    # Here trigger GitHub Action with lab_data (not implemented in this snippet)
    send_lab_ready_email(username, password, request.email)
    return {"message": "Lab started", "username": username}

@app.get("/lab-status/{username}", response_model=LabStatus)
def lab_status(username: str, token: dict = Depends(verify_token)):
    lab_raw = redis_client.get(f"lab:{username}")
    if not lab_raw:
        raise HTTPException(status_code=404, detail="Lab not found")
    lab = json.loads(lab_raw)
    ttl = redis_client.ttl(f"lab:{username}")
    return LabStatus(**lab, ttl_seconds=ttl)

