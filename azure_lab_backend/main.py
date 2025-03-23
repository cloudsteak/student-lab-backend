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
import httpx

app = FastAPI(docs_url="/docs", redoc_url=None)
security = HTTPBearer()

redis_client = Redis(
    host=os.getenv("REDIS_HOST", "localhost"),
    port=int(os.getenv("REDIS_PORT", 6379)),
    db=int(os.getenv("REDIS_DB", 0))
)

TTL = int(os.getenv("LAB_TTL_SECONDS", 3600))

async def trigger_github_workflow(username: str, password: str, lab_name: str):
    repo = os.getenv("GITHUB_REPO")
    workflow_filename = os.getenv("GITHUB_WORKFLOW_FILENAME")
    github_token = os.getenv("GITHUB_TOKEN")

    url = f"https://api.github.com/repos/{repo}/actions/workflows/{workflow_filename}/dispatches"
    headers = {
        "Authorization": f"Bearer {github_token}",
        "Accept": "application/vnd.github+json"
    }
    json_data = {
        "ref": "main",
        "inputs": {
            "username": username,
            "password": password,
            "lab_name": lab_name
        }
    }

    async with httpx.AsyncClient() as client:
        response = await client.post(url, headers=headers, json=json_data)
        if response.status_code >= 300:
            raise HTTPException(status_code=500, detail=f"Failed to trigger workflow: {response.text}")


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
async def start_lab(request: LabRequest, token: dict = Depends(verify_token)):
    username, password = generate_credentials()
    lab_data = {
        "lab_name": request.lab_name,
        "username": username,
        "password": password
    }

    # ✅ 1. GitHub Actions elindítása
    await trigger_github_workflow(username, password, request.lab_name)

    # ✅ 2. Indulási idő mentése + Redis TTL beállítása
    now = datetime.utcnow().isoformat()
    lab_data["started_at"] = now
    redis_client.setex(f"lab:{username}", TTL, json.dumps(lab_data))

    # ✅ 3. Email küldés
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

