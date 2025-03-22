# --- azure_lab_backend/models.py ---

from pydantic import BaseModel, EmailStr

class LabRequest(BaseModel):
    lab_name: str
    email: EmailStr

class LabStatus(BaseModel):
    lab_name: str
    username: str
    password: str
    ttl_seconds: int
