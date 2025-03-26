# --- lab-backend/models.py ---

from pydantic import BaseModel, EmailStr

class LabRequest(BaseModel):
    lab_name: str
    cloud_provider: str
    email: EmailStr

class LabReadyRequest(BaseModel):
    username: str
    status: str # expected values: "ready", "error", "failed", etc.

class LabStatus(BaseModel):
    lab_name: str
    username: str
    password: str
    ttl_seconds: int
