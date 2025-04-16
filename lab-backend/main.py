# --- lab-backend/main.py ---

from fastapi import FastAPI, Depends, HTTPException, status
from .utils import generate_credentials, get_rsa_key
from .emailer import send_lab_ready_email
from .models import LabRequest, LabStatus, LabReadyRequest, LabDeleteRequest, status_map
from redis import Redis
import requests
import os
import json
from jose import jwt, JWTError
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
import httpx
from datetime import datetime
from fastapi.responses import JSONResponse

app = FastAPI(docs_url="/docs", redoc_url=None)
security = HTTPBearer()

redis_client = Redis(
    host=os.getenv("REDIS_HOST", "localhost"),
    port=int(os.getenv("REDIS_PORT", 6379)),
    db=int(os.getenv("REDIS_DB", 0))
)

TTL = int(os.getenv("LAB_TTL_SECONDS", 3600))
WORDPRESS_WEBHOOK_URL = os.getenv("WORDPRESS_WEBHOOK_URL")
WORDPRESS_SECRET_KEY = os.getenv("WORDPRESS_SECRET_KEY")

async def trigger_github_workflow(username: str, password: str, lab: str = "basic", action: str = "apply", cloud_provider: str = "aws"):
    repo = os.getenv("GITHUB_REPO")
    workflow_filename = cloud_provider + os.getenv("GITHUB_WORKFLOW_FILENAME")
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
        "cloud_provider": request.cloud_provider,
        "username": username,
        "password": password,
        "email": request.email,
        "status": "pending"
    }

    # Store lab metadata (no TTL!)
    redis_client.set(f"lab:{username}", json.dumps(lab_data))

    # Trigger GitHub Actions - Apply
    await trigger_github_workflow(
        username=username, 
        password=password, 
        lab=request.lab_name, 
        action="apply", 
        cloud_provider=request.cloud_provider)

    return {
        "message": "Lab started",
        "username": username,
        "password": password
    }

@app.get("/lab-status/all")
def list_labs(token: dict = Depends(verify_token)):
    has_permission(token, "read:labs")

    keys = redis_client.keys("lab:*")
    labs = []

    for key in keys:
        username = key.decode().split(":")[1]
        lab_raw = redis_client.get(key)
        if not lab_raw:
            continue
        lab_data = json.loads(lab_raw)
        ttl = redis_client.ttl(key)
        print(f"Lab {username} - TTL: {ttl}")
        lab_data["username"] = username
        lab_data["ttl_seconds"] = ttl
        lab_data["ttl_in_seconds"] = TTL
        labs.append(lab_data)

    return JSONResponse(content={"labs": labs})

@app.get("/lab-status/{username}", response_model=LabStatus)
def lab_status(username: str, token: dict = Depends(verify_token)):
    lab_raw = redis_client.get(f"lab:{username}")
    if not lab_raw:
        raise HTTPException(status_code=404, detail="Lab not found")
    lab = json.loads(lab_raw)

    ttl = 0
    if "started_at" in lab:
        started = datetime.fromisoformat(lab["started_at"])
        ttl = max(0, TTL - int((datetime.utcnow() - started).total_seconds()))

    return LabStatus(**lab, ttl_seconds=ttl)

@app.post("/lab-ready")
async def lab_ready(request: LabReadyRequest, token: dict = Depends(verify_token)):
    has_permission(token, "notify:lab")
    username = request.username
    status_value = request.status.lower()

    key = f"lab:{username}"
    lab_raw = redis_client.get(key)
    if not lab_raw:
        raise HTTPException(status_code=404, detail="Lab not found")

    lab_data = json.loads(lab_raw)

    if lab_data.get("status") == "ready":
        return {"message": "Lab already marked as ready"}

    now = datetime.utcnow().isoformat()

    if status_value != "ready":
        lab_data["status"] = status_value
        lab_data["error_at"] = now
        redis_client.set(key, json.dumps(lab_data))

        # WordPress webhook küldés hibás státusz esetén is
        if WORDPRESS_WEBHOOK_URL and WORDPRESS_SECRET_KEY:
            webhook_url = f"{WORDPRESS_WEBHOOK_URL}?secret_key={WORDPRESS_SECRET_KEY}"
            webhook_status = status_map.get(status_value, "pending")
            payload = {
                "email": lab_data.get("email"),
                "lab_id": lab_data.get("lab_id", "unknown"),
                "status": webhook_status
            }
            try:
                response = requests.post(webhook_url, json=payload)
                response.raise_for_status()
            except requests.RequestException as e:
                print(f"[WARNING] Failed to call WordPress webhook: {str(e)}")

        return {"message": f"Lab {username} reported status: {status_value}"}

    # success case (status_value == "ready")
    lab_data["status"] = "ready"
    lab_data["started_at"] = now 
    lab_data["ttl_in_seconds"] = TTL

    send_lab_ready_email(
        username,
        lab_data["password"],
        lab_data["email"],
        cloud_provider=lab_data["cloud_provider"]
    )
    redis_client.set(key, json.dumps(lab_data))

    # WordPress webhook hívása ready esetén
    if WORDPRESS_WEBHOOK_URL and WORDPRESS_SECRET_KEY:
        webhook_url = f"{WORDPRESS_WEBHOOK_URL}?secret_key={WORDPRESS_SECRET_KEY}"
        webhook_status = status_map.get("ready", "pending")
        payload = {
            "email": lab_data.get("email"),
            "lab_id": lab_data.get("lab_id", "unknown"),
            "status": webhook_status
        }
        try:
            response = requests.post(webhook_url, json=payload)
            response.raise_for_status()
        except requests.RequestException as e:
            print(f"[WARNING] Failed to call WordPress webhook: {str(e)}")

    return {"message": f"Lab {username} marked as ready, email sent, WordPress notified"}


@app.post("/lab-delete-internal")
def delete_lab_internal(request: LabDeleteRequest, token: dict = Depends(verify_token)):
    has_permission(token, "delete:lab")

    key = f"lab:{request.username}"
    result = redis_client.delete(key)

    if result == 1:
        return {"message": f"Redis key '{key}' deleted successfully"}
    else:
        raise HTTPException(status_code=404, detail=f"Redis key '{key}' not found")


@app.post("/clean-up-lab")
async def clean_up_lab(request: LabDeleteRequest, token: dict = Depends(verify_token)):
    has_permission(token, "delete:lab")

    key = f"lab:{request.username}"
    lab_raw = redis_client.get(key)
    if not lab_raw:
        raise HTTPException(status_code=404, detail="Lab not found")

    lab = json.loads(lab_raw)

    if "password" not in lab or "lab_name" not in lab:
        raise HTTPException(status_code=500, detail="Lab data is incomplete in Redis")

    await trigger_github_workflow(
        username=request.username,
        password="dummy",
        lab=lab["lab_name"],
        action="destroy",
        cloud_provider=lab["cloud_provider"]
    )

    return {"message": f"Destroy action triggered for {request.username}"}
