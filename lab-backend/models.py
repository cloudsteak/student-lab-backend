# --- lab-backend/models.py ---

from pydantic import BaseModel, EmailStr

class LabRequest(BaseModel):
    lab_name: str
    cloud_provider: str
    email: EmailStr
    lab_ttl: int

class LabReadyRequest(BaseModel):
    username: str
    status: str # expected values: "ready", "error", "failed", etc.


class LabDeleteRequest(BaseModel):
    username: str


status_map = {
    "ready": "success",
    "failed": "error"
}

class VerifyRequest(BaseModel):
    user: str
    email: EmailStr
    cloud: str  # pl. "azure"
    lab: str    # pl. "basic"
