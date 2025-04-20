# --- lab-backend/emailer.py ---

import sib_api_v3_sdk
from sib_api_v3_sdk.rest import ApiException
import os

def send_lab_ready_email(username: str, password: str, recipient: str, cloud_provider: str, ttl_seconds: int):
    configuration = sib_api_v3_sdk.Configuration()
    configuration.api_key["api-key"] = os.getenv("BREVO_API_KEY")
    azure_portal_url = os.getenv("PORTAL_AZURE_URL")
    aws_portal_url = os.getenv("PORTAL_AWS_URL")
    cloud_console_url = ""
    if cloud_provider == "azure":
        cloud_console_url = azure_portal_url
        username = username + "@cloudsteak.com"
    elif cloud_provider == "aws":
        cloud_console_url = aws_portal_url
        

    api_instance = sib_api_v3_sdk.TransactionalEmailsApi(sib_api_v3_sdk.ApiClient(configuration))
    subject = "A labor környezeted elkészült!"
    sender = {"name": "CloudMentor", "email": os.getenv("EMAIL_SENDER")}
    to = [{"email": recipient}]
    html_content = f"""
    <p>Helló!</p>
    <p>A gyakorló környezeted elkészült. Kattinnts <a href='{cloud_console_url}' target='_blank'>ide a webes bejelentkezéshez</a>.</p>
    <p><b>Felhasználónév:</b> {username}<br><b>Jelszó:</b> {password}</p>
    <p><strong>A lab {int(float(ttl_seconds) / 60)} perc múlva automatikusan törlődik.</strong></p>
    
    
    <p>Üdv, <br>Kiss Tibor - CloudMentor</p>
    """

    email = sib_api_v3_sdk.SendSmtpEmail(to=to, html_content=html_content, sender=sender, subject=subject)
    try:
        api_instance.send_transac_email(email)
    except ApiException as e:
        print(f"Hiba történt az e-mail küldéskor: {e}")
