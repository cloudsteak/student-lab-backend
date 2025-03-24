# --- azure_lab_backend/main.py ---

from fastapi import FastAPI, Depends, HTTPException, status
from .utils import generate_credentials, get_rsa_key
from .emailer import send_lab_ready_email
from .models import LabRequest, LabStatus
from redis import Redis
import os
import json
from jose import jwt, JWTError
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
import httpx
from datetime import datetime




app = FastAPI(docs_url="/docs", redoc_url=None)
security = HTTPBearer()

redis_client = Redis(
    host=os.getenv("REDIS_HOST", "localhost"),
    port=int(os.getenv("REDIS_PORT", 6379)),
    db=int(os.getenv("REDIS_DB", 0))
)

TTL = int(os.getenv("LAB_TTL_SECONDS", 3600))

async def trigger_github_workflow(username: str, password: str, lab: str = "basic", action: str = "apply"):
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
            "lab": lab,
            "action": action,
            "student_username": username,
            "student_password": password
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
        rsa_key = get_rsa_key(token)
        payload = jwt.decode(
            token,
            key=rsa_key,
            audience=os.getenv("AUTH0_AUDIENCE"),
            issuer=f"https://{os.getenv('AUTH0_DOMAIN')}/",
            algorithms=[os.getenv("AUTH0_ALGORITHMS", "RS256")]
        )
        return payload
    except JWTError as e:
        print("JWT verification failed:", e)
        raise HTTPException(status_code=status.HTTP_401_UNAUTHORIZED, detail="Invalid token")

# Permission check helper
def has_permission(token: dict, required: str):
    permissions = token.get("permissions", [])
    if required not in permissions:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=f"Missing required permission: {required}"
        )
# --- Endpoints ---
@app.get("/")
def root():
    return {"message": "Student Lab Backend API is up and running"}

@app.post("/start-lab")
async def start_lab(request: LabRequest, token: dict = Depends(verify_token)):
    has_permission(token, "create:lab")
    username, password = generate_credentials()
    
    lab_data = {
        "lab_name": request.lab_name,
        "username": username,
        "password": password,
        "email": request.email,
        "status": "pending"
    }

    # Store lab metadata (no TTL yet!)
    redis_client.set(f"lab:{username}", json.dumps(lab_data))

    # Trigger GitHub Actions
    await trigger_github_workflow(username, password, request.lab_name)

    return {
        "message": "Lab started",
        "username": username,
        "password": password
    }


@app.get("/lab-status/{username}", response_model=LabStatus)
def lab_status(username: str, token: dict = Depends(verify_token)):
    lab_raw = redis_client.get(f"lab:{username}")
    if not lab_raw:
        raise HTTPException(status_code=404, detail="Lab not found")
    lab = json.loads(lab_raw)
    ttl = redis_client.ttl(f"lab:{username}")
    return LabStatus(**lab, ttl_seconds=ttl)

@app.post("/lab-ready")
async def lab_ready(request: Request):
    has_permission(token, "notify:lab")
    body = await request.json()
    username = body.get("username")

    key = f"lab:{username}"
    lab_raw = redis_client.get(key)
    if not lab_raw:
        raise HTTPException(status_code=404, detail="Lab not found")

    lab_data = json.loads(lab_raw)
    if lab_data.get("status") == "ready":
        return {"message": "Lab already marked as ready"}


    now = datetime.utcnow().isoformat()
    lab_data["status"] = "ready"
    lab_data["started_at"] = now

    send_lab_ready_email(username, lab_data["password"], lab_data["email"])
    redis_client.setex(key, TTL, json.dumps(lab_data))

    return {"message": f"Lab {username} marked as ready and email sent"}
